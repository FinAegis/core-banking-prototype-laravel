import { Router, Request, Response } from 'express';
import {
  populateProvedUnshield,
  generateUnshieldProof,
  gasEstimateForUnshield,
} from '@railgun-community/wallet';
import { NetworkName, RailgunERC20Amount } from '@railgun-community/shared-models';
import { ethers } from 'ethers';
import { isEngineReady, resolveNetworkName, resolveChainId, logger } from '../engine';
import { walletRegistry } from './wallet';
import { EngineNotReadyError, ValidationError, errorResponse } from '../utils/errors';

const router = Router();

/**
 * POST /unshield
 * Build an unshield (withdraw) transaction from the RAILGUN privacy pool.
 * Generates the ZK proof server-side, then returns unsigned calldata.
 */
router.post('/unshield', async (req: Request, res: Response) => {
  try {
    if (!isEngineReady()) throw new EngineNotReadyError();

    const { walletId, encryptionKey, recipientAddress, tokenAddress, amount, network } = req.body;
    if (!walletId || !encryptionKey || !recipientAddress || !tokenAddress || !amount || !network) {
      throw new ValidationError(
        'walletId, encryptionKey, recipientAddress, tokenAddress, amount, and network are required',
      );
    }

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
    const chain = { type: 0, id: chainId };

    const erc20Amount: RailgunERC20Amount = {
      tokenAddress,
      amount: ethers.BigNumber.from(amount),
    };

    // Generate the unshield proof (this is computationally expensive)
    logger.info('Generating unshield proof...', { walletId, network });
    await generateUnshieldProof(
      networkName,
      walletInfo.railgunAddress,
      encryptionKey,
      [erc20Amount],
      [], // No NFTs
      recipientAddress,
      false, // Not a relayer fee
      0, // No relay adaptation
      () => {}, // Progress callback
    );

    // Populate the proved unshield transaction
    const { transaction, nullifiers } = await populateProvedUnshield(
      networkName,
      walletInfo.railgunAddress,
      [erc20Amount],
      [], // No NFTs
      recipientAddress,
      false,
      0,
    );

    logger.info('Unshield transaction built', {
      walletId,
      network,
      recipient: recipientAddress,
      tokenAddress,
      amount,
    });

    res.json({
      success: true,
      data: {
        transaction: {
          to: transaction.to,
          data: transaction.data,
          value: transaction.value?.toString() || '0',
        },
        nullifiers,
        network,
      },
    });
  } catch (err) {
    const { statusCode, body } = errorResponse(err);
    res.status(statusCode).json(body);
  }
});

export default router;
