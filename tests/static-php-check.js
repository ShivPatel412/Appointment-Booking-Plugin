const fs = require('fs');
const path = require('path');
const parser = require('php-parser');

const engine = new parser.Engine({ parser: { extractDoc: true }, ast: { withPositions: true } });
const root = path.resolve(__dirname, '..');
const files = [];

function walk(directory) {
  for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
    if (['node_modules', '.git', 'work', 'outputs'].includes(entry.name)) continue;
    const target = path.join(directory, entry.name);
    if (entry.isDirectory()) walk(target);
    else if (entry.name.endsWith('.php')) files.push(target);
  }
}

walk(root);
for (const file of files) engine.parseCode(fs.readFileSync(file, 'utf8'), file);
console.log(`Parsed ${files.length} PHP files successfully.`);
