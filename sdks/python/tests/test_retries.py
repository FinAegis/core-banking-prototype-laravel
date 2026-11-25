"""
Retry behaviour of the FinAegis client.

The client sends a failed request again after a connection error, a 5xx or
a 429. A POST may only be sent again with the same Idempotency-Key, so the
API can answer the repeat with the first result instead of running it a
second time.

These tests run the real requests/urllib3 stack against a local HTTP server
that answers from a script, so they cover the transport's own retries too.
"""

import json
import re
import threading
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

import pytest

from finaegis import FinAegis
from finaegis.exceptions import FinAegisError

UUID_V4 = re.compile(r'^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$')

CREATED = (201, {'data': {'uuid': 't-1', 'from_account': 'acc-1', 'to_account': 'acc-2',
                          'amount': 2500, 'asset_code': 'USD', 'status': 'pending',
                          'created_at': '2026-11-24T10:00:00Z'}})
DROP = 'drop'


class ScriptedServer:
    """Answers each request with the next scripted outcome and records it."""

    def __init__(self):
        self.outcomes = []
        self.requests = []
        server = self

        class Handler(BaseHTTPRequestHandler):
            protocol_version = 'HTTP/1.1'

            def log_message(self, *args):
                pass

            def _handle(self):
                length = int(self.headers.get('Content-Length') or 0)
                body = self.rfile.read(length) if length else b''
                server.requests.append({
                    'method': self.command,
                    'path': self.path,
                    'headers': dict(self.headers),
                    'body': body,
                })
                index = min(len(server.requests), len(server.outcomes)) - 1
                outcome = server.outcomes[index]

                if outcome == DROP:
                    # Close the connection without an answer, as when a load
                    # balancer or the network drops it mid-request.
                    self.close_connection = True
                    return

                status, payload = outcome
                data = json.dumps(payload).encode()
                self.send_response(status)
                self.send_header('Content-Type', 'application/json')
                self.send_header('Content-Length', str(len(data)))
                self.end_headers()
                self.wfile.write(data)

            do_GET = do_POST = do_PUT = do_DELETE = _handle

        self.httpd = ThreadingHTTPServer(('127.0.0.1', 0), Handler)
        self.thread = threading.Thread(target=self.httpd.serve_forever, daemon=True)
        self.thread.start()

    @property
    def base_url(self):
        return f'http://127.0.0.1:{self.httpd.server_address[1]}/v2/'

    def keys(self):
        return [r['headers'].get('Idempotency-Key') for r in self.requests]

    def close(self):
        self.httpd.shutdown()
        self.httpd.server_close()


@pytest.fixture
def server():
    s = ScriptedServer()
    yield s
    s.close()


@pytest.fixture(autouse=True)
def no_backoff(monkeypatch):
    """Retry back-off is not under test; do not sleep for it."""
    import time
    monkeypatch.setattr(time, 'sleep', lambda seconds: None)


def make_client(server, max_retries):
    client = FinAegis(api_key='test', base_url=server.base_url, max_retries=max_retries, timeout=5)
    client.session.trust_env = False  # ignore proxy settings from the environment
    return client


def test_retries_a_transfer_with_the_same_key_after_a_5xx(server):
    server.outcomes = [(503, {}), (502, {}), CREATED]
    client = make_client(server, max_retries=2)

    transfer = client.transfers.create('acc-1', 'acc-2', 2500)

    assert transfer.uuid == 't-1'
    assert len(server.requests) == 3
    keys = server.keys()
    assert UUID_V4.match(keys[0])
    assert keys == [keys[0]] * 3


def test_retries_with_the_same_key_after_the_connection_drops(server):
    server.outcomes = [DROP, CREATED]
    client = make_client(server, max_retries=1)

    client.transfers.create('acc-1', 'acc-2', 2500)

    keys = server.keys()
    assert len(keys) == 2
    assert UUID_V4.match(keys[0])
    assert keys[1] == keys[0]


def test_uses_a_new_key_for_each_transfer(server):
    server.outcomes = [CREATED]
    client = make_client(server, max_retries=0)

    client.transfers.create('acc-1', 'acc-2', 2500)
    client.transfers.create('acc-1', 'acc-2', 2500)

    keys = server.keys()
    assert UUID_V4.match(keys[0]) and UUID_V4.match(keys[1])
    assert keys[0] != keys[1]


def test_uses_the_callers_key_and_repeats_it_on_retry(server):
    server.outcomes = [(500, {}), CREATED]
    client = make_client(server, max_retries=1)

    client.transfers.create('acc-1', 'acc-2', 2500, idempotency_key='order-2026-11-24-0001')

    assert server.keys() == ['order-2026-11-24-0001', 'order-2026-11-24-0001']


def test_makes_exactly_one_attempt_when_max_retries_is_zero(server):
    server.outcomes = [(503, {'message': 'Service unavailable'}), CREATED]
    client = make_client(server, max_retries=0)

    with pytest.raises(FinAegisError) as error:
        client.transfers.create('acc-1', 'acc-2', 2500)

    assert error.value.status_code == 503
    assert len(server.requests) == 1


def test_a_dropped_post_is_attempted_once_when_max_retries_is_zero(server):
    server.outcomes = [DROP, CREATED]
    client = make_client(server, max_retries=0)

    with pytest.raises(Exception):
        client.transfers.create('acc-1', 'acc-2', 2500)

    assert len(server.requests) == 1


def test_does_not_retry_a_post_that_has_no_idempotency_key(server):
    server.outcomes = [(503, {}), CREATED]
    client = make_client(server, max_retries=3)

    # A caller that turns the key off on purpose: the request must not be
    # sent again, because the API could not tell it is a repeat.
    with pytest.raises(FinAegisError) as error:
        client.post('/transfers', json={}, idempotency_key=False)

    assert error.value.status_code == 503
    assert len(server.requests) == 1
    assert server.keys() == [None]


def test_does_not_resend_a_post_without_a_key_after_the_connection_drops(server):
    server.outcomes = [DROP, CREATED]
    client = make_client(server, max_retries=3)

    with pytest.raises(Exception):
        client.post('/transfers', json={}, idempotency_key=False)

    assert len(server.requests) == 1


def test_adds_a_key_to_deposits_and_withdrawals(server):
    server.outcomes = [(201, {'data': {'id': 'tx-1', 'account_uuid': 'acc-1', 'type': 'deposit',
                                       'amount': 1000, 'asset_code': 'USD', 'status': 'pending',
                                       'created_at': '2026-11-24T10:00:00Z'}})]
    client = make_client(server, max_retries=0)

    client.accounts.deposit('acc-1', 1000, idempotency_key='deposit-key-0000000001')
    client.accounts.withdraw('acc-1', 1000)

    keys = server.keys()
    assert keys[0] == 'deposit-key-0000000001'
    assert UUID_V4.match(keys[1])


def test_get_requests_carry_no_key_and_are_still_retried(server):
    server.outcomes = [(503, {}), (200, {'data': {'uuid': 't-1', 'from_account': 'acc-1',
                                                  'to_account': 'acc-2', 'amount': 2500,
                                                  'asset_code': 'USD', 'status': 'completed',
                                                  'created_at': '2026-11-24T10:00:00Z'}})]
    client = make_client(server, max_retries=1)

    client.transfers.get('t-1')

    assert len(server.requests) == 2
    assert server.keys() == [None, None]
