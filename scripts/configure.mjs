#!/usr/bin/env node
// Statik sayfalardaki alan adını ve marka adını kendi sitenize göre ayarlar.
// Kullanım: node scripts/configure.mjs --domain https://siteniz.com [--name "Sitenizin Adı"]
import { readdirSync, readFileSync, writeFileSync, statSync } from 'node:fs';
import { join, extname } from 'node:path';
import { fileURLToPath } from 'node:url';

const args = Object.fromEntries(
  process.argv.slice(2).reduce((pairs, arg, i, all) => (arg.startsWith('--') ? [...pairs, [arg.slice(2), all[i + 1]]] : pairs), [])
);
if (!args.domain || !/^https?:\/\/[^/]+$/.test(args.domain)) {
  console.error('Kullanım: node scripts/configure.mjs --domain https://siteniz.com [--name "Sitenizin Adı"]');
  process.exit(1);
}

const ROOT = fileURLToPath(new URL('../public_html/', import.meta.url));
const TEXT = new Set(['.html', '.xml', '.txt', '.js', '.php', '.webmanifest', '.json']);
let changed = 0;

(function walk(dir) {
  for (const name of readdirSync(dir)) {
    const path = join(dir, name);
    if (statSync(path).isDirectory()) { walk(path); continue; }
    if (!TEXT.has(extname(name))) continue;
    const before = readFileSync(path, 'utf8');
    let after = before.replaceAll('https://example.com', args.domain);
    if (args.name) after = after.replaceAll('HedefAkademi', args.name);
    if (after !== before) { writeFileSync(path, after); changed++; }
  }
})(ROOT);

console.log(`${changed} dosya güncellendi.`);
