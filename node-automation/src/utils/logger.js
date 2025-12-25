const LOG_LEVELS = {
  debug: 0,
  info: 1,
  warn: 2,
  error: 3,
};

const COLORS = {
  reset: '\x1b[0m',
  red: '\x1b[31m',
  yellow: '\x1b[33m',
  blue: '\x1b[34m',
  green: '\x1b[32m',
};

const currentLogLevel = process.env.DEBUG === 'true' ? LOG_LEVELS.debug : LOG_LEVELS.info;

export const logger = {
  debug: (msg) => {
    if (currentLogLevel <= LOG_LEVELS.debug) {
      console.log(`${COLORS.blue}[DEBUG]${COLORS.reset} ${msg}`);
    }
  },

  info: (msg) => {
    if (currentLogLevel <= LOG_LEVELS.info) {
      console.log(`${COLORS.green}[INFO]${COLORS.reset} ${msg}`);
    }
  },

  warn: (msg) => {
    if (currentLogLevel <= LOG_LEVELS.warn) {
      console.log(`${COLORS.yellow}[WARN]${COLORS.reset} ${msg}`);
    }
  },

  error: (msg, obj) => {
    if (currentLogLevel <= LOG_LEVELS.error) {
      const suffix = obj ? ` ${JSON.stringify(obj, null, 2)}` : '';
      console.error(`${COLORS.red}[ERROR]${COLORS.reset} ${msg}${suffix}`);
    }
  },
};

export default logger;