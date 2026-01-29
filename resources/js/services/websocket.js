/**
 * WebSocket Service for FinAegis Core Banking Platform
 *
 * Provides a unified interface for connecting to the Soketi WebSocket server
 * and subscribing to tenant-specific channels for real-time updates.
 *
 * Usage:
 * ```javascript
 * import { WebSocketService } from './services/websocket';
 *
 * const ws = new WebSocketService();
 * await ws.connect();
 *
 * // Subscribe to order book updates
 * ws.subscribeToOrderBook((data) => {
 *   console.log('Order book updated:', data);
 * });
 *
 * // Subscribe to balance updates
 * ws.subscribeToBalance((data) => {
 *   console.log('Balance updated:', data);
 * });
 * ```
 */

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Make Pusher available globally for Laravel Echo
window.Pusher = Pusher;

/**
 * @typedef {Object} WebSocketConfig
 * @property {boolean} enabled
 * @property {string} key
 * @property {string} cluster
 * @property {string} ws_host
 * @property {number} ws_port
 * @property {number} wss_port
 * @property {boolean} force_tls
 * @property {boolean} encrypted
 * @property {string} auth_endpoint
 */

/**
 * @typedef {Object} ChannelInfo
 * @property {string} name
 * @property {string} type
 * @property {string} description
 * @property {string[]} events
 */

export class WebSocketService {
    /**
     * @type {Echo|null}
     */
    echo = null;

    /**
     * @type {WebSocketConfig|null}
     */
    config = null;

    /**
     * @type {ChannelInfo[]}
     */
    channels = [];

    /**
     * @type {Map<string, any>}
     */
    subscriptions = new Map();

    /**
     * @type {boolean}
     */
    connected = false;

    /**
     * @type {string|null}
     */
    tenantId = null;

    /**
     * Event callbacks
     * @type {Map<string, Function[]>}
     */
    callbacks = new Map();

    /**
     * Initialize the WebSocket service.
     *
     * @param {Object} options
     * @param {string} [options.apiBaseUrl='/api'] - Base URL for API calls
     * @param {string} [options.authToken] - Bearer token for authentication
     */
    constructor(options = {}) {
        this.apiBaseUrl = options.apiBaseUrl || '/api';
        this.authToken = options.authToken || null;
    }

    /**
     * Set the authentication token.
     *
     * @param {string} token
     */
    setAuthToken(token) {
        this.authToken = token;
    }

    /**
     * Fetch WebSocket configuration from the server.
     *
     * @returns {Promise<WebSocketConfig>}
     */
    async fetchConfig() {
        const response = await fetch(`${this.apiBaseUrl}/websocket/config`);
        if (!response.ok) {
            throw new Error('Failed to fetch WebSocket config');
        }
        this.config = await response.json();
        return this.config;
    }

    /**
     * Fetch available channels for the authenticated user.
     *
     * @returns {Promise<ChannelInfo[]>}
     */
    async fetchChannels() {
        if (!this.authToken) {
            throw new Error('Authentication token required to fetch channels');
        }

        const response = await fetch(`${this.apiBaseUrl}/websocket/channels`, {
            headers: {
                'Authorization': `Bearer ${this.authToken}`,
                'Accept': 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error('Failed to fetch WebSocket channels');
        }

        const data = await response.json();
        this.channels = data.channels || [];

        // Extract tenant ID from channel name
        if (this.channels.length > 0) {
            const match = this.channels[0].name.match(/tenant\.(\d+)/);
            if (match) {
                this.tenantId = match[1];
            }
        }

        return this.channels;
    }

    /**
     * Connect to the WebSocket server.
     *
     * @returns {Promise<void>}
     */
    async connect() {
        if (!this.config) {
            await this.fetchConfig();
        }

        if (!this.config.enabled) {
            console.warn('WebSocket broadcasting is disabled');
            return;
        }

        this.echo = new Echo({
            broadcaster: 'pusher',
            key: this.config.key,
            cluster: this.config.cluster,
            wsHost: this.config.ws_host,
            wsPort: this.config.ws_port,
            wssPort: this.config.wss_port,
            forceTLS: this.config.force_tls,
            encrypted: this.config.encrypted,
            enabledTransports: ['ws', 'wss'],
            disableStats: true,
            authEndpoint: this.config.auth_endpoint,
            auth: {
                headers: {
                    Authorization: this.authToken ? `Bearer ${this.authToken}` : '',
                },
            },
        });

        // Wait for connection
        return new Promise((resolve, reject) => {
            this.echo.connector.pusher.connection.bind('connected', () => {
                this.connected = true;
                console.log('WebSocket connected');
                resolve();
            });

            this.echo.connector.pusher.connection.bind('error', (error) => {
                console.error('WebSocket connection error:', error);
                reject(error);
            });
        });
    }

    /**
     * Disconnect from the WebSocket server.
     */
    disconnect() {
        if (this.echo) {
            this.echo.disconnect();
            this.connected = false;
            this.subscriptions.clear();
            console.log('WebSocket disconnected');
        }
    }

    /**
     * Subscribe to a private channel.
     *
     * @param {string} channelSuffix - Channel suffix (e.g., 'exchange', 'accounts')
     * @returns {any} The channel subscription
     */
    subscribeToChannel(channelSuffix) {
        if (!this.echo || !this.tenantId) {
            throw new Error('WebSocket not connected or tenant ID not set');
        }

        const channelName = channelSuffix
            ? `tenant.${this.tenantId}.${channelSuffix}`
            : `tenant.${this.tenantId}`;

        if (this.subscriptions.has(channelName)) {
            return this.subscriptions.get(channelName);
        }

        const channel = this.echo.private(channelName);
        this.subscriptions.set(channelName, channel);
        return channel;
    }

    /**
     * Subscribe to order book updates.
     *
     * @param {Function} callback - Called when order book is updated
     * @returns {void}
     */
    subscribeToOrderBook(callback) {
        const channel = this.subscribeToChannel('exchange');
        channel.listen('.orderbook.updated', callback);
        this.addCallback('orderbook.updated', callback);
    }

    /**
     * Subscribe to trade executions.
     *
     * @param {Function} callback - Called when a trade is executed
     * @returns {void}
     */
    subscribeToTrades(callback) {
        const channel = this.subscribeToChannel('exchange');
        channel.listen('.trade.executed', callback);
        this.addCallback('trade.executed', callback);
    }

    /**
     * Subscribe to balance updates.
     *
     * @param {Function} callback - Called when balance is updated
     * @returns {void}
     */
    subscribeToBalance(callback) {
        const channel = this.subscribeToChannel('accounts');
        channel.listen('.balance.updated', callback);
        this.addCallback('balance.updated', callback);
    }

    /**
     * Subscribe to portfolio updates.
     *
     * @param {Function} callback - Called when portfolio is updated
     * @returns {void}
     */
    subscribeToPortfolio(callback) {
        const channel = this.subscribeToChannel('accounts');
        channel.listen('.portfolio.updated', callback);
        this.addCallback('portfolio.updated', callback);
    }

    /**
     * Subscribe to NAV calculations.
     *
     * @param {Function} callback - Called when NAV is calculated
     * @returns {void}
     */
    subscribeToNav(callback) {
        const channel = this.subscribeToChannel('accounts');
        channel.listen('.nav.calculated', callback);
        this.addCallback('nav.calculated', callback);
    }

    /**
     * Subscribe to transaction notifications.
     *
     * @param {Function} callback - Called when a transaction occurs
     * @returns {void}
     */
    subscribeToTransactions(callback) {
        const channel = this.subscribeToChannel('transactions');
        channel.listen('.transaction.credited', callback);
        channel.listen('.transaction.debited', callback);
        channel.listen('.transaction.pending', callback);
        this.addCallback('transaction', callback);
    }

    /**
     * Subscribe to multi-sig wallet approval updates.
     *
     * @param {Function} callback - Called when approval status changes
     * @returns {void}
     */
    subscribeToMultiSigApprovals(callback) {
        const channel = this.subscribeToChannel('wallet.multi-sig');
        channel.listen('.approval.created', callback);
        channel.listen('.signature.submitted', callback);
        channel.listen('.approval.completed', callback);
        this.addCallback('multisig', callback);
    }

    /**
     * Subscribe to compliance alerts (admin only).
     *
     * @param {Function} callback - Called when a compliance alert is created
     * @returns {void}
     */
    subscribeToComplianceAlerts(callback) {
        const channel = this.subscribeToChannel('compliance');
        channel.listen('.alert.created', callback);
        channel.listen('.review.required', callback);
        channel.listen('.threshold.exceeded', callback);
        this.addCallback('compliance', callback);
    }

    /**
     * Unsubscribe from a channel.
     *
     * @param {string} channelSuffix - Channel suffix to unsubscribe from
     */
    unsubscribe(channelSuffix) {
        if (!this.echo || !this.tenantId) {
            return;
        }

        const channelName = channelSuffix
            ? `tenant.${this.tenantId}.${channelSuffix}`
            : `tenant.${this.tenantId}`;

        if (this.subscriptions.has(channelName)) {
            this.echo.leave(channelName);
            this.subscriptions.delete(channelName);
        }
    }

    /**
     * Add a callback for an event type.
     *
     * @param {string} eventType
     * @param {Function} callback
     * @private
     */
    addCallback(eventType, callback) {
        if (!this.callbacks.has(eventType)) {
            this.callbacks.set(eventType, []);
        }
        this.callbacks.get(eventType).push(callback);
    }

    /**
     * Check if WebSocket is connected.
     *
     * @returns {boolean}
     */
    isConnected() {
        return this.connected;
    }

    /**
     * Get connection state.
     *
     * @returns {string}
     */
    getConnectionState() {
        if (!this.echo) {
            return 'disconnected';
        }
        return this.echo.connector.pusher.connection.state;
    }
}

// Export a singleton instance for convenience
export const websocket = new WebSocketService();

export default WebSocketService;
