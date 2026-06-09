const sqlite3 = require('sqlite3').verbose();
const path = require('path');

const dbPath = path.resolve(__dirname, 'database.sqlite');
const db = new sqlite3.Database(dbPath, (err) => {
    if (err) {
        console.error('Error opening database', err.message);
    } else {
        console.log('Connected to the SQLite database.');
        
        // Initialize tables
        db.run(`CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT UNIQUE,
            has_voted BOOLEAN DEFAULT 0,
            reminder_sent DATETIME,
            login_code TEXT,
            login_code_expires DATETIME
        )`, () => {
            db.get(`SELECT * FROM users WHERE email = 'admin@afsos.org'`, (err, row) => {
                if (!row) {
                    db.run(`INSERT INTO users (email) VALUES ('admin@afsos.org')`);
                    console.log('Admin user admin@afsos.org seeded successfully.');
                }
            });
        });

        db.run(`CREATE TABLE IF NOT EXISTS candidates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT,
            profession TEXT,
            location TEXT,
            pdf_url TEXT
        )`);

        db.run(`CREATE TABLE IF NOT EXISTS votes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            candidate_id INTEGER,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(candidate_id) REFERENCES candidates(id)
        )`);

        db.run(`CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT
        )`, () => {
            // Seed settings si vide
            db.get(`SELECT * FROM settings WHERE key = 'max_votes'`, (err, row) => {
                if (!row) {
                    db.run(`INSERT INTO settings (key, value) VALUES ('max_votes', '5')`);
                }
            });
            db.get(`SELECT * FROM settings WHERE key = 'election_end_date'`, (err, row) => {
                if (!row) {
                    // Par défaut, fin dans 7 jours
                    const defaultDate = new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().slice(0, 16);
                    db.run(`INSERT INTO settings (key, value) VALUES ('election_end_date', ?)`, [defaultDate]);
                }
            });
        });
    }
});

module.exports = db;
