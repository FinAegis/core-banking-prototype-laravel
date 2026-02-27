import { Router, Request, Response } from 'express';
import {
  populateProvedTransfer,
  generateTransferProof,
} from '@railgun-community/wallet';
import { NetworkName, RailgunERC20Amount } from '@railgun-community/shared-models';
import { ethers } from 'ethers';
import { isEngineReady, resolveNetworkName, resolveChainId, logger } from '../engine';
import { walletRegistry } from './wallet';
import { EngineNotReadyError, ValidationError, errorResponse } from '../utils/errors';

const router = Router();

/**
 * POST /transfer
 * Build a private transfer between two RAILGUN (0zk) addresses.
 * Generates the ZK proof server-side, then returns unsigned calldata.
 */
router.post('/transfer', async (req: Request, res: Response) => {
  try {
    if (!isEngineReady()) throw new EngineNotReadyError();

    const { walletId, encryptionKey, recipientRailgunAddress, tokenAddress, amount, network } = req.body;
    if (!walletId || !encryptionKey || !recipientRailgunAddress || !tokenAddress || !amount || !network) {
      throw new ValidationError(
        'walletId, encryptionKey, recipientRailgunAddress, tokenAddress, amount, and network are required',
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

    // Generate the transfer proof
    logger.info('Generating transfer proof...', { walletId, network });
    await generateTransferProof(
      networkName,
      walletInfo.railgunAddress,
      encryptionKey,
      false, // showSenderAddressToRecipient
      undefined, // memoText
      [erc20Amount],
      [], // No NFTs
      recipientRailgunAddress,
      false, // Not a relayer fee
      0, // No relay adaptation
      () => {}, // Progress callback
    );

    // Populate the proved transfer transaction
    const { transaction, nullifiers } = await populateProvedTransfer(
      networkName,
      walletInfo.railgunAddress,
      false,
      undefined,
      [erc20Amount],
      [], // No NFTs
      recipientRailgunAddress,
      false,
      0,
    );

    logger.info('Private transfer transaction built', {
      walletId,
      network,
      recipient: recipientRailgunAddress.substring(0, 16) + '...',
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
