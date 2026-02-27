import 'dotenv/config';
import express from 'express';
import cors from 'cors';
import helmet from 'helmet';
import { initializeEngine, logger } from './engine';
import { authMiddleware } from './middleware/auth';
import healthRouter from './routes/health';
import walletRouter from './routes/wallet';
import shieldRouter from './routes/shield';
import unshieldRouter from './routes/unshield';
import transferRouter from './routes/transfer';
import merkleRouter from './routes/merkle';

const app = express();
const PORT = parseInt(process.env.PORT || '3100', 10);
const HOST = process.env.HOST || '127.0.0.1';

// Middleware
app.use(helmet());
app.use(cors({ origin: false })); // Only internal access
app.use(express.json({ limit: '1mb' }));

// Health endpoint is public (for load balancer checks)
app.use(healthRouter);

// All other routes require auth
app.use(authMiddleware);
app.use(walletRouter);
app.use(shieldRouter);
app.use(unshieldRouter);
app.use(transferRouter);
app.use(merkleRouter);

// Global error handler
app.use((err: Error, _req: express.Request, res: express.Response, _next: express.NextFunction) => {
  logger.error('Unhandled error', { error: err.message, stack: err.stack });
  res.status(500).json({
    success: false,
    error: { code: 'INTERNAL_ERROR', message: 'An unexpected error occurred' },
  });
});

// Start server after engine initialization
async function main() {
  logger.info('Starting RAILGUN Bridge Service...');

  try {
    await initializeEngine();
  } catch (err) {
    logger.error('Failed to initialize RAILGUN Engine', {
      error: err instanceof Error ? err.message : String(err),
    });
    logger.warn('Bridge will start but engine endpoints will return 503 until engine is ready');
  }

  app.listen(PORT, HOST, () => {
    logger.info(`RAILGUN Bridge Service listening on ${HOST}:${PORT}`);
  });
}

main().catch((err) => {
  logger.error('Fatal error during startup', {
    error: err instanceof Error ? err.message : String(err),
  });
  process.exit(1);
});
