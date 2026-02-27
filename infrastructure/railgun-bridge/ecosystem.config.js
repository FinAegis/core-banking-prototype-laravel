module.exports = {
  apps: [
    {
      name: 'railgun-bridge',
      script: 'dist/index.js',
      cwd: __dirname,
      instances: 1, // Must be 1 — RAILGUN Engine holds in-process state
      exec_mode: 'fork',
      autorestart: true,
      watch: false,
      max_memory_restart: '1G',
      env: {
        NODE_ENV: 'production',
        PORT: 3100,
        HOST: '127.0.0.1',
      },
      env_development: {
        NODE_ENV: 'development',
        PORT: 3100,
        HOST: '127.0.0.1',
      },
      // Graceful shutdown
      kill_timeout: 10000,
      listen_timeout: 30000,
      // Logging
      error_file: './logs/error.log',
      out_file: './logs/output.log',
      log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
      merge_logs: true,
    },
  ],
};
