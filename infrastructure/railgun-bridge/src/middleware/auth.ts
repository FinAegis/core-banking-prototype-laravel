import { Request, Response, NextFunction } from 'express';
import { logger } from '../engine';

const BRIDGE_SECRET = process.env.BRIDGE_SECRET || '';

export function authMiddleware(req: Request, res: Response, next: NextFunction): void {
  if (!BRIDGE_SECRET) {
    logger.warn('BRIDGE_SECRET not configured — rejecting all requests');
    res.status(500).json({
      success: false,
      error: { code: 'CONFIG_ERROR', message: 'Bridge secret not configured' },
    });
    return;
  }

  const authHeader = req.headers.authorization;
  if (!authHeader || !authHeader.startsWith('Bearer ')) {
    res.status(401).json({
      success: false,
      error: { code: 'UNAUTHORIZED', message: 'Missing or invalid Authorization header' },
    });
    return;
  }

  const token = authHeader.slice(7);
  if (token !== BRIDGE_SECRET) {
    logger.warn('Invalid bridge secret in request');
    res.status(403).json({
      success: false,
      error: { code: 'FORBIDDEN', message: 'Invalid bridge secret' },
    });
    return;
  }

  next();
}
