import type { Page } from '@playwright/test';
import { expect } from '@playwright/test';

/**
 * Accessibility violation severity levels
 */
export type ViolationSeverity = 'critical' | 'serious' | 'moderate' | 'minor';

/**
 * Accessibility violation interface
 */
export interface AccessibilityViolation {
  id: string;
  impact: ViolationSeverity;
  description: string;
  help: string;
  helpUrl: string;
  nodes: Array<{
    html: string;
    target: string[];
    failureSummary: string;
  }>;
}

/**
 * Accessibility scan result
 */
export interface AccessibilityScanResult {
  url: string;
  timestamp: string;
  violations: AccessibilityViolation[];
  passes: number;
  incomplete: number;
  violationsBySeverity: {
    critical: number;
    serious: number;
    moderate: number;
    minor: number;
  };
}

/**
 * Accessibility scanner configuration
 */
export interface ScannerConfig {
  /**
   * Rules to run (default: all WCAG 2.2 Level AA rules supported by axe)
   */
  runOnly?: {
    type: 'tag' | 'rule';
    values: string[];
  };

  /**
   * Rules to disable
   */
  disabledRules?: string[];

  /**
   * Selectors to exclude from scanning
   */
  exclude?: string[];

  /**
   * Whether to fail on violations
   */
  failOnViolations?: boolean;

  /**
   * Minimum severity level to fail on
   */
  failOnSeverity?: ViolationSeverity;

  /**
   * Whether to output detailed results
   */
  detailedOutput?: boolean;
}

/**
 * Default scanner configuration
 */
const DEFAULT_CONFIG: ScannerConfig = {
  runOnly: {
    type: 'tag',
    values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'],
  },
  disabledRules: [],
  exclude: [],
  failOnViolations: true,
  failOnSeverity: 'serious',
  detailedOutput: true,
};

/**
 * Inject axe-core into the page
 */
async function injectAxe(page: Page): Promise<void> {
  await page.addScriptTag({
    url: 'https://cdnjs.cloudflare.com/ajax/libs/axe-core/4.10.2/axe.min.js',
  });
}

/**
 * Run accessibility scan on a page
 */
export async function scanPage(page: Page, config: Partial<ScannerConfig> = {}): Promise<AccessibilityScanResult> {
  const mergedConfig = { ...DEFAULT_CONFIG, ...config };

  // Inject axe-core
  await injectAxe(page);

  // Run axe scan
  const results = await page.evaluate(async (evalConfig) => {
    // @ts-expect-error - axe is injected globally
    const axe = window.axe;

    const axeConfig: any = {
      runOnly: evalConfig.runOnly,
      rules: {},
    };

    // Disable specific rules
    if (evalConfig.disabledRules && evalConfig.disabledRules.length > 0) {
      evalConfig.disabledRules.forEach((ruleId: string) => {
        axeConfig.rules[ruleId] = { enabled: false };
      });
    }

    // Run axe
    const axeResults = await axe.run(
      evalConfig.exclude && evalConfig.exclude.length > 0
        ? { exclude: evalConfig.exclude.map((sel: string) => [sel]) }
        : document,
      axeConfig,
    );

    return axeResults;
  }, mergedConfig);

  // Process results
  const violations: AccessibilityViolation[] = results.violations.map((violation: any) => ({
    id: violation.id,
    impact: violation.impact as ViolationSeverity,
    description: violation.description,
    help: violation.help,
    helpUrl: violation.helpUrl,
    nodes: violation.nodes.map((node: any) => ({
      html: node.html,
      target: node.target,
      failureSummary: node.failureSummary || '',
    })),
  }));

  // Count violations by severity
  const violationsBySeverity = {
    critical: violations.filter((v) => v.impact === 'critical').length,
    serious: violations.filter((v) => v.impact === 'serious').length,
    moderate: violations.filter((v) => v.impact === 'moderate').length,
    minor: violations.filter((v) => v.impact === 'minor').length,
  };

  return {
    url: page.url(),
    timestamp: new Date().toISOString(),
    violations,
    passes: results.passes.length,
    incomplete: results.incomplete.length,
    violationsBySeverity,
  };
}

/**
 * Assert that a page has no accessibility violations
 */
export async function assertNoViolations(page: Page, config: Partial<ScannerConfig> = {}): Promise<void> {
  const results = await scanPage(page, config);
  const mergedConfig = { ...DEFAULT_CONFIG, ...config };

  if (!mergedConfig.failOnViolations) {
    return;
  }

  // Filter violations by severity
  const severityLevels: ViolationSeverity[] = ['critical', 'serious', 'moderate', 'minor'];
  const minSeverityIndex = severityLevels.indexOf(mergedConfig.failOnSeverity || 'serious');

  const relevantViolations = results.violations.filter((v) => {
    const violationIndex = severityLevels.indexOf(v.impact);
    return violationIndex <= minSeverityIndex;
  });

  if (relevantViolations.length > 0) {
    const errorMessage = formatViolationsError(results, relevantViolations, mergedConfig);
    expect(relevantViolations, errorMessage).toHaveLength(0);
  }
}

/**
 * Format violations into a readable error message
 */
function formatViolationsError(
  results: AccessibilityScanResult,
  violations: AccessibilityViolation[],
  config: ScannerConfig,
): string {
  let message = `\n\n🚨 Accessibility Violations Found on ${results.url}\n`;
  message += `━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n`;

  message += `Summary:\n`;
  message += `  • Critical: ${results.violationsBySeverity.critical}\n`;
  message += `  • Serious:  ${results.violationsBySeverity.serious}\n`;
  message += `  • Moderate: ${results.violationsBySeverity.moderate}\n`;
  message += `  • Minor:    ${results.violationsBySeverity.minor}\n`;
  message += `  • Passes:   ${results.passes}\n\n`;

  violations.forEach((violation, index) => {
    message += `${index + 1}. [${violation.impact.toUpperCase()}] ${violation.help}\n`;
    message += `   ID: ${violation.id}\n`;
    message += `   Description: ${violation.description}\n`;
    message += `   Help: ${violation.helpUrl}\n`;

    if (config.detailedOutput) {
      message += `   Affected elements (${violation.nodes.length}):\n`;
      violation.nodes.slice(0, 3).forEach((node, nodeIndex) => {
        message += `     ${nodeIndex + 1}. ${node.target.join(' > ')}\n`;
        message += `        HTML: ${node.html.substring(0, 100)}${node.html.length > 100 ? '...' : ''}\n`;
        if (node.failureSummary) {
          message += `        Issue: ${node.failureSummary.split('\n')[0]}\n`;
        }
      });
      if (violation.nodes.length > 3) {
        message += `     ... and ${violation.nodes.length - 3} more\n`;
      }
    }
    message += `\n`;
  });

  message += `━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n`;

  return message;
}

/**
 * Generate an HTML report of accessibility violations
 */
export function generateHtmlReport(results: AccessibilityScanResult): string {
  const severityColors = {
    critical: '#d32f2f',
    serious: '#f57c00',
    moderate: '#fbc02d',
    minor: '#388e3c',
  };

  return `
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Accessibility Report - ${results.url}</title>
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
    .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    h1 { color: #333; margin-top: 0; }
    .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin: 20px 0; }
    .summary-card { padding: 15px; border-radius: 6px; text-align: center; }
    .summary-card h3 { margin: 0 0 10px 0; font-size: 14px; text-transform: uppercase; }
    .summary-card .count { font-size: 32px; font-weight: bold; }
    .critical-card { background: #ffebee; color: #d32f2f; }
    .serious-card { background: #fff3e0; color: #f57c00; }
    .moderate-card { background: #fffde7; color: #f9a825; }
    .minor-card { background: #e8f5e9; color: #388e3c; }
    .passes-card { background: #e3f2fd; color: #1976d2; }
    .violation { margin: 20px 0; padding: 20px; border-left: 4px solid; border-radius: 4px; background: #fafafa; }
    .violation h3 { margin-top: 0; }
    .violation-meta { color: #666; font-size: 14px; margin: 10px 0; }
    .node { margin: 10px 0; padding: 10px; background: white; border-radius: 4px; font-size: 13px; }
    .node code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-family: 'Courier New', monospace; }
    a { color: #1976d2; text-decoration: none; }
    a:hover { text-decoration: underline; }
  </style>
</head>
<body>
  <div class="container">
    <h1>🔍 Accessibility Report</h1>
    <p><strong>URL:</strong> ${results.url}</p>
    <p><strong>Scanned:</strong> ${new Date(results.timestamp).toLocaleString()}</p>
    
    <div class="summary">
      <div class="summary-card critical-card">
        <h3>Critical</h3>
        <div class="count">${results.violationsBySeverity.critical}</div>
      </div>
      <div class="summary-card serious-card">
        <h3>Serious</h3>
        <div class="count">${results.violationsBySeverity.serious}</div>
      </div>
      <div class="summary-card moderate-card">
        <h3>Moderate</h3>
        <div class="count">${results.violationsBySeverity.moderate}</div>
      </div>
      <div class="summary-card minor-card">
        <h3>Minor</h3>
        <div class="count">${results.violationsBySeverity.minor}</div>
      </div>
      <div class="summary-card passes-card">
        <h3>Passes</h3>
        <div class="count">${results.passes}</div>
      </div>
    </div>
    
    ${
      results.violations.length === 0
        ? '<h2>✅ No violations found!</h2>'
        : `
      <h2>Violations (${results.violations.length})</h2>
      ${results.violations
        .map(
          (v, i) => `
        <div class="violation" style="border-color: ${severityColors[v.impact]}">
          <h3>${i + 1}. ${v.help}</h3>
          <div class="violation-meta">
            <strong>Impact:</strong> <span style="color: ${severityColors[v.impact]}">${v.impact.toUpperCase()}</span> | 
            <strong>Rule ID:</strong> ${v.id} | 
            <a href="${v.helpUrl}" target="_blank">Learn more →</a>
          </div>
          <p>${v.description}</p>
          <details>
            <summary><strong>Affected Elements (${v.nodes.length})</strong></summary>
            ${v.nodes
              .map(
                (node, j) => `
              <div class="node">
                <strong>${j + 1}.</strong> <code>${node.target.join(' > ')}</code><br>
                <strong>HTML:</strong> <code>${node.html.substring(0, 150)}${node.html.length > 150 ? '...' : ''}</code>
                ${node.failureSummary ? `<br><strong>Issue:</strong> ${node.failureSummary.split('\n')[0]}` : ''}
              </div>
            `,
              )
              .join('')}
          </details>
        </div>
      `,
        )
        .join('')}
    `
    }
  </div>
</body>
</html>
  `.trim();
}
