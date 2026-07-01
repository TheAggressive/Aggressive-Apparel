const mode = process.env.LHCI_MODE || 'budget';
const baseUrl = (process.env.LHCI_BASE_URL || 'http://localhost:9910').replace(
  /\/$/,
  ''
);
const productUrl = process.env.LHCI_PRODUCT_URL || '';
const chromeProfile = process.env.LHCI_CHROME_PROFILE || '';
const isBudget = mode === 'budget';
const isReport = mode === 'report';
const aggregationMethod = isBudget ? 'median' : 'optimistic';
const enforcedLevel = isBudget ? 'error' : 'warn';

const urls =
  mode === 'quick'
    ? [`${baseUrl}/shop/`]
    : [`${baseUrl}/`, `${baseUrl}/shop/`, productUrl].filter(Boolean);

const assertions = isReport
  ? {}
  : {
      'categories:performance': ['warn', { minScore: 0.8, aggregationMethod }],
      'largest-contentful-paint': [
        enforcedLevel,
        { maxNumericValue: 4000, aggregationMethod },
      ],
      'cumulative-layout-shift': [
        enforcedLevel,
        { maxNumericValue: 0.1, aggregationMethod },
      ],
      'total-blocking-time': [
        enforcedLevel,
        { maxNumericValue: 300, aggregationMethod },
      ],
      'server-response-time': [
        'warn',
        { maxNumericValue: 1800, aggregationMethod },
      ],
      'resource-summary:script:size': [
        enforcedLevel,
        { maxNumericValue: 500 * 1024, aggregationMethod },
      ],
      'resource-summary:stylesheet:size': [
        enforcedLevel,
        { maxNumericValue: 300 * 1024, aggregationMethod },
      ],
      'resource-summary:total:size': [
        enforcedLevel,
        { maxNumericValue: 3 * 1024 * 1024, aggregationMethod },
      ],
      'resource-summary:total:count': [
        enforcedLevel,
        { maxNumericValue: 120, aggregationMethod },
      ],
    };

module.exports = {
  ci: {
    collect: {
      chromePath: process.env.CHROME_PATH,
      numberOfRuns: isBudget ? 3 : 1,
      url: urls,
      settings: {
        chromeFlags: [
          '--headless=new',
          '--disable-gpu',
          chromeProfile ? `--user-data-dir=${chromeProfile}` : '',
        ]
          .filter(Boolean)
          .join(' '),
        maxWaitForLoad: 45000,
        onlyCategories: ['performance'],
        preset: 'desktop',
      },
    },
    assert: {
      assertions,
      includePassedAssertions: isBudget,
    },
    upload: {
      target: 'filesystem',
      outputDir: `./.lighthouseci/reports/${mode}`,
      reportFilenamePattern:
        '%%HOSTNAME%%-%%PATHNAME%%-%%DATETIME%%.report.%%EXTENSION%%',
    },
  },
};
