import { chromium } from 'playwright';
import AxeBuilder from '@axe-core/playwright';
import fs from 'node:fs/promises';

const BASE = process.env.BASE_URL || 'https://sonika.com.co';
const PATHS = (process.env.PATHS || '/,/artista/demo-artist').split(',').map(s => s.trim()).filter(Boolean);
const VIEWPORTS = [
  { name: 'xs-320', width: 320, height: 740 },
  { name: 'sm-390', width: 390, height: 844 },
  { name: 'md-768', width: 768, height: 1024 },
  { name: 'lg-1024', width: 1024, height: 768 },
  { name: 'xl-1440', width: 1440, height: 900 },
  { name: 'uw-1920', width: 1920, height: 1080 },
];
const OUT = new URL('../artifacts/adaptive/', import.meta.url);
const problems = [];

await fs.mkdir(OUT, { recursive: true });
const browser = await chromium.launch();
for (const vp of VIEWPORTS) {
  const ctx = await browser.newContext({ viewport: { width: vp.width, height: vp.height }, deviceScaleFactor: 1 });
  const page = await ctx.newPage();
  for (const p of PATHS) {
    const url = BASE.replace(/\/$/, '') + p;
    try {
      await page.goto(url, { waitUntil: 'networkidle', timeout: 45000 });
      await page.waitForTimeout(400);
      const overflow = await page.evaluate(() => ({
        sw: document.documentElement.scrollWidth,
        iw: window.innerWidth,
      }));
      if (overflow.sw > overflow.iw + 1) problems.push(`${vp.name} ${p}: scroll horizontal (${overflow.sw} > ${overflow.iw})`);
      const name = `${vp.name}${p.replace(/[^a-z0-9]+/gi, '_') || '_home'}`;
      await page.screenshot({ path: new URL(`${name}.png`, OUT).pathname, fullPage: true });
      const axe = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze();
      for (const v of axe.violations.filter(v => ['serious', 'critical'].includes(v.impact))) {
        problems.push(`${vp.name} ${p}: axe ${v.id} (${v.impact}) x${v.nodes.length}`);
      }
    } catch (e) {
      problems.push(`${vp.name} ${p}: ERROR ${e.message}`);
    }
  }
  await ctx.close();
}
await browser.close();
if (problems.length) {
  console.error('ADAPTIVE ISSUES:\n' + problems.map(x => ' - ' + x).join('\n'));
  process.exit(1);
}
console.log('Adaptive snapshots OK: ' + VIEWPORTS.length + ' viewports x ' + PATHS.length + ' pages');
