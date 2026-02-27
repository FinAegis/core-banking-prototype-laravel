import winston from 'winston';
import {
  NetworkName,
  NETWORK_CONFIG,
  FallbackProviderJsonConfig,
} from '@railgun-community/shared-models';
import {
  startRailgunEngine,
  loadProvider,
  setLoggers,
  getProver,
} from '@railgun-community/wallet';
import * as fs from 'fs';
import * as path from 'path';

export const logger = winston.createLogger({
  level: process.env.LOG_LEVEL || 'info',
  format: winston.format.combine(
    winston.format.timestamp(),
    winston.format.json(),
  ),
  transports: [new winston.transports.Console()],
});

// Chain ID → NetworkName mapping for RAILGUN
export const SUPPORTED_NETWORKS: Record<string, NetworkName> = {
  ethereum: NetworkName.Ethereum,
  polygon: NetworkName.Polygon,
  arbitrum: NetworkName.Arbitrum,
  bsc: NetworkName.BNBChain,
};

// Chain ID numeric values
export const CHAIN_IDS: Record<string, number> = {
  ethereum: 1,
  polygon: 137,
  arbitrum: 42161,
  bsc: 56,
};

let engineReady = false;
const loadedNetworks: Set<string> = new Set();

export function isEngineReady(): boolean {
  return engineReady;
}

export function getLoadedNetworks(): string[] {
  return Array.from(loadedNetworks);
}

function getArtifactsDir(): string {
  const dir = process.env.RAILGUN_ARTIFACTS_DIR || path.join(process.cwd(), 'data', 'artifacts');
  if (!fs.existsSync(dir)) {
    fs.mkdirSync(dir, { recursive: true });
  }
  return dir;
}

function getRpcUrl(network: string): string | undefined {
  const envKey = `${network.toUpperCase()}_RPC_URL`;
  return process.env[envKey];
}

function buildFallbackProviderConfig(rpcUrl: string): FallbackProviderJsonConfig {
  return {
    chainId: 0, // Will be set per network
    providers: [
      {
        provider: rpcUrl,
        priority: 1,
        weight: 1,
        maxLogsPerBatch: 1,
        stallTimeout: 2500,
      },
    ],
  };
}

export async function initializeEngine(): Promise<void> {
  logger.info('Initializing RAILGUN Engine...');

  const artifactsDir = getArtifactsDir();
  const dbPath = path.join(artifactsDir, 'engine.db');

  // Custom logging bridge
  setLoggers(
    (msg: string) => logger.info(`[RAILGUN] ${msg}`),
    (msg: string) => logger.error(`[RAILGUN] ${msg}`),
  );

  // Start the engine
  await startRailgunEngine(
    'finaegis-railgun-bridge',
    dbPath,
    // Debug mode in non-production
    process.env.NODE_ENV !== 'production',
    // Artifact store — RAILGUN downloads proving artifacts here
    {
      getFile: async (filePath: string) => {
        const fullPath = path.join(artifactsDir, filePath);
        if (fs.existsSync(fullPath)) {
          return fs.readFileSync(fullPath);
        }
        return undefined;
      },
      storeFile: async (filePath: string, data: Buffer | string | Uint8Array) => {
        const fullPath = path.join(artifactsDir, filePath);
        const dir = path.dirname(fullPath);
        if (!fs.existsSync(dir)) {
          fs.mkdirSync(dir, { recursive: true });
        }
        fs.writeFileSync(fullPath, data);
      },
      fileExists: async (filePath: string) => {
        return fs.existsSync(path.join(artifactsDir, filePath));
      },
    },
  );

  logger.info('RAILGUN Engine started');

  // Load providers for each configured network
  for (const [networkKey, networkName] of Object.entries(SUPPORTED_NETWORKS)) {
    const rpcUrl = getRpcUrl(networkKey);
    if (!rpcUrl) {
      logger.warn(`No RPC URL configured for ${networkKey} — skipping`);
      continue;
    }

    try {
      const chainId = CHAIN_IDS[networkKey];
      const providerConfig = buildFallbackProviderConfig(rpcUrl);
      providerConfig.chainId = chainId;

      const { feesSerialized } = await loadProvider(
        providerConfig,
        networkName,
        false, // polling not needed for bridge
      );

      loadedNetworks.add(networkKey);
      logger.info(`Loaded provider for ${networkKey} (chainId: ${chainId})`, {
        network: networkKey,
        fees: feesSerialized,
      });
    } catch (err) {
      logger.error(`Failed to load provider for ${networkKey}`, {
        network: networkKey,
        error: err instanceof Error ? err.message : String(err),
      });
    }
  }

  // Load prover (downloads artifacts if needed)
  try {
    const prover = getProver();
    logger.info('RAILGUN Prover initialized');
  } catch (err) {
    logger.warn('Prover initialization deferred — will init on first proof request', {
      error: err instanceof Error ? err.message : String(err),
    });
  }

  engineReady = true;
  logger.info('RAILGUN Engine initialization complete', {
    loadedNetworks: Array.from(loadedNetworks),
  });
}

export function resolveNetworkName(network: string): NetworkName {
  const name = SUPPORTED_NETWORKS[network.toLowerCase()];
  if (!name) {
    throw new Error(`Unsupported network: ${network}`);
  }
  return name;
}

export function resolveChainId(network: string): number {
  const id = CHAIN_IDS[network.toLowerCase()];
  if (!id) {
    throw new Error(`Unknown chain ID for network: ${network}`);
  }
  return id;
}
