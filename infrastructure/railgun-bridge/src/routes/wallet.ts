import { Router, Request, Response } from 'express';
import {
  createRailgunWallet,
  getRailgunWalletAddressData,
  refreshBalances,
  walletForID,
} from '@railgun-community/wallet';
import { NetworkName, RailgunWalletInfo } from '@railgun-community/shared-models';
import {
  isEngineReady,
  resolveNetworkName,
  resolveChainId,
  logger,
  SUPPORTED_NETWORKS,
} from '../engine';
import { EngineNotReadyError, ValidationError, errorResponse } from '../utils/errors';

const router = Router();

// In-memory wallet registry (keyed by a caller-provided ID)
const walletRegistry: Map<string, RailgunWalletInfo> = new Map();

/**
 * POST /wallet/create
 * Create a RAILGUN wallet from mnemonic + encryption key.
 */
router.post('/wallet/create', async (req: Request, res: Response) => {
  try {
    if (!isEngineReady()) throw new EngineNotReadyError();

    const { mnemonic, encryptionKey, walletId } = req.body;
    if (!mnemonic || !encryptionKey || !walletId) {
      throw new ValidationError('mnemonic, encryptionKey, and walletId are required');
    }

    const walletInfo = await createRailgunWallet(
      encryptionKey,
      mnemonic,
      undefined, // creationBlockNumbers — use defaults
    );

    walletRegistry.set(walletId, walletInfo);

    logger.info('RAILGUN wallet created', {
      walletId,
      railgunAddress: walletInfo.railgunAddress,
    });

    res.status(201).json({
      success: true,
      data: {
        wallet_id: walletId,
        railgun_address: walletInfo.railgunAddress,
      },
    });
  } catch (err) {
    const { statusCode, body } = errorResponse(err);
    res.status(statusCode).json(body);
  }
});

/**
 * GET /wallet/:id/balances
 * Get shielded token balances for a wallet on a given network.
 */
router.get('/wallet/:id/balances', async (req: Request, res: Response) => {
  try {
    if (!isEngineReady()) throw new EngineNotReadyError();

    const walletId = req.params.id;
    const network = (req.query.network as string) || 'polygon';

    const walletInfo = walletRegistry.get(walletId);
    if (!walletInfo) {
      res.status(404).json({
        success: false,
        error: { code: 'WALLET_NOT_FOUND', message: `Wallet ${walletId} not found` },
      });
      return;
    }

    const networkName = resolveNetworkName(network);
    const chainId = resolveChainId(network);
    const chain = { type: 0, id: chainId }; // type 0 = EVM

    const wallet = walletForID(walletInfo.id);
    const balances = await wallet.getTokenBalances(chain, false);

    // Convert bigint balances to string format
    const formattedBalances: Record<string, string> = {};
    for (const [tokenAddress, balance] of Object.entries(balances)) {
      formattedBalances[tokenAddress] = balance.toString();
    }

    res.json({
      success: true,
      data: {
        wallet_id: walletId,
        network,
        balances: formattedBalances,
      },
    });
  } catch (err) {
    const { statusCode, body } = errorResponse(err);
    res.status(statusCode).json(body);
  }
});

/**
 * POST /wallet/scan
 * Trigger a wallet balance rescan.
 */
router.post('/wallet/scan', async (req: Request, res: Response) => {
  try {
    if (!isEngineReady()) throw new EngineNotReadyError();

    const { walletId, network } = req.body;
    if (!walletId) {
      throw new ValidationError('walletId is required');
    }

    const walletInfo = walletRegistry.get(walletId);
    if (!walletInfo) {
      res.status(404).json({
        success: false,
        error: { code: 'WALLET_NOT_FOUND', message: `Wallet ${walletId} not found` },
      });
      return;
    }

    const targetNetwork = network || 'polygon';
    const chainId = resolveChainId(targetNetwork);
    const chain = { type: 0, id: chainId };

    await refreshBalances(chain, [walletInfo.id]);

    logger.info('Wallet scan triggered', { walletId, network: targetNetwork });

    res.json({
      success: true,
      data: {
        wallet_id: walletId,
        network: targetNetwork,
        status: 'scan_initiated',
      },
    });
  } catch (err) {
    const { statusCode, body } = errorResponse(err);
    res.status(statusCode).json(body);
  }
});

export default router;
export { walletRegistry };
