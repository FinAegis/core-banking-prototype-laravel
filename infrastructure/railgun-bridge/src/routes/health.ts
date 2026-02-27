import { Router, Request, Response } from 'express';
import { isEngineReady, getLoadedNetworks, SUPPORTED_NETWORKS } from '../engine';

const router = Router();

router.get('/health', (_req: Request, res: Response) => {
  const ready = isEngineReady();
  const loadedNetworks = getLoadedNetworks();

  res.status(ready ? 200 : 503).json({
    success: ready,
    data: {
      status: ready ? 'healthy' : 'initializing',
      engine_ready: ready,
      supported_networks: Object.keys(SUPPORTED_NETWORKS),
      loaded_networks: loadedNetworks,
      uptime_seconds: Math.floor(process.uptime()),
      version: '1.0.0',
    },
  });
});

export default router;
