import { Router, Request, Response } from 'express';
import {
  populateShield,
  gasEstimateForShield,
} from '@railgun-community/wallet';
import { NetworkName, RailgunERC20Amount } from '@railgun-community/shared-models';
import { ethers } from 'ethers';
import { isEngineReady, resolveNetworkName, resolveChainId, logger } from '../engine';
import { walletRegistry } from './wallet';
import { EngineNotReadyError, ValidationError, errorResponse } from '../utils/errors';

const router = Router();

/**
 * POST /shield
 * Build a shield (deposit) transaction for the RAILGUN privacy pool.
 * Returns unsigned calldata that the frontend/relayer submits on-chain.
 */
router.post('/shield', async (req: Request, res: Response) => {
  try {
    if (!isEngineReady()) throw new EngineNotReadyError();

    const { walletId, tokenAddress, amount, network } = req.body;
    if (!walletId || !tokenAddress || !amount || !network) {
      throw new ValidationError('walletId, tokenAddress, amount, and network are required');
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

    // Build ERC20 amount
    const shieldAmount: RailgunERC20Amount = {
      tokenAddress,
      amount: ethers.BigNumber.from(amount),
    };

    // Populate shield transaction
    const { transaction, nullifiers } = await populateShield(
      networkName,
      walletInfo.railgunAddress,
      [shieldAmount],
      [], // No NFTs
    );

    // Estimate gas
    let gasEstimate: string | undefined;
    try {
      const estimate = await gasEstimateForShield(
        networkName,
        walletInfo.railgunAddress,
        [shieldAmount],
        [], // No NFTs
      );
      gasEstimate = estimate.gasEstimate.toString();
    } catch (gasErr) {
      logger.warn('Gas estimation failed for shield', {
        error: gasErr instanceof Error ? gasErr.message : String(gasErr),
      });
    }

    logger.info('Shield transaction built', {
      walletId,
      network,
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
        gas_estimate: gasEstimate,
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
