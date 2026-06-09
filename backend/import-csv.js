const fs = require('fs');
const csv = require('csv-parser');
const db = require('./db');
const path = require('path');

const csvPath = path.join(__dirname, 'uploads', 'rcp-memberships-export-2026-05-06.csv');

const results = [];

fs.createReadStream(csvPath)
  .pipe(csv())
  .on('data', (data) => {
    const userEmail = data['E-mail utilisateur'] || data.email;
    if(userEmail) results.push(userEmail.trim().toLowerCase());
  })
  .on('end', () => {
    db.serialize(() => {
        db.run('BEGIN TRANSACTION');
        const stmt = db.prepare(`INSERT OR IGNORE INTO users (email) VALUES (?)`);
        results.forEach(email => {
            stmt.run(email);
        });
        stmt.finalize();
        db.run('COMMIT', () => {
             console.log(`Import terminé. ${results.length} emails traités et ajoutés à la base de données !`);
             db.close();
        });
    });
  });
