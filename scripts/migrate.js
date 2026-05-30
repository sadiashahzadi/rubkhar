'use strict';

/**
 * Migration script — run once to set up the managed MySQL database.
 * Usage: npm run migrate
 *
 * Reads DATABASE_URL from .env (or environment), connects with
 * multipleStatements:true, runs schema.sql then seed.sql.
 */

require('dotenv').config();

const fs = require('fs');
const path = require('path');
const mysql = require('mysql2/promise');

const DATABASE_URL = process.env.DATABASE_URL;
if (!DATABASE_URL) {
  console.error('ERROR: DATABASE_URL environment variable is not set.');
  process.exit(1);
}

const schemaPath = path.join(__dirname, '..', 'db', 'schema.sql');
const seedPath = path.join(__dirname, '..', 'db', 'seed.sql');

function isLocalDatabase(urlString) {
  try {
    const dbUrl = new URL(urlString);
    return dbUrl.hostname === '127.0.0.1' || dbUrl.hostname === 'localhost';
  } catch {
    return false;
  }
}

async function runFile(connection, filePath) {
  const fileName = path.basename(filePath);
  console.log(`\nRunning ${fileName}...`);

  const sql = fs.readFileSync(filePath, 'utf8');

  // Remove SQL comments before splitting into statements.
  const cleanedSql = sql.replace(/--.*(\r?\n|$)/g, '\n');
  const statements = cleanedSql
    .split(';')
    .map((s) => s.trim())
    .filter((s) => s.length > 0);

  for (let i = 0; i < statements.length; i++) {
    const stmt = statements[i];
    try {
      await connection.query(stmt);
      // Print first 60 chars of statement as progress indicator
      const preview = stmt.replace(/\s+/g, ' ').substring(0, 60);
      console.log(`  [${i + 1}/${statements.length}] OK: ${preview}...`);
    } catch (err) {
      console.error(`  [${i + 1}/${statements.length}] FAILED: ${stmt.substring(0, 80)}`);
      console.error(`  Error: ${err.message}`);
      throw err;
    }
  }

  console.log(`${fileName} completed.`);
}

async function main() {
  console.log('Connecting to database...');

  const connectionOptions = {
    uri: DATABASE_URL,
    multipleStatements: true,
  };

  if (!isLocalDatabase(DATABASE_URL)) {
    connectionOptions.ssl = {
      minVersion: 'TLSv1.2',
      rejectUnauthorized: true,
    };
  }

  const connection = await mysql.createConnection(connectionOptions);

  console.log('Connected.');

  try {
    await runFile(connection, schemaPath);
    await runFile(connection, seedPath);
    console.log('\nMigration complete!');
  } catch (err) {
    console.error('\nMigration FAILED:', err.message);
    process.exit(1);
  } finally {
    await connection.end();
  }
}

main();
