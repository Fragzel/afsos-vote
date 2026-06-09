const db = require('./db');

db.serialize(() => {
    // Insert Admin User
    db.run(`INSERT OR IGNORE INTO users (email, has_voted) VALUES ('admin@afsos.org', 0)`);
    
    // Insert Mock Candidates
    const candidates = [
        ['Dr. Dupont Pierre', 'Oncologue', 'CHU Lyon', ''],
        ['Dr. Bernard Marie', 'Oncologue', 'IGR Paris', ''],
        ['Dr. Garnier Lucie', 'Radiothérapeute', 'Bordeaux', ''],
        ['Dr. Valois Renée', 'Oncologue', 'Strasbourg', ''],
        ['Nicolas Lefebvre', 'Infirmier', 'Nantes', ''],
        ['Aline Faure', 'Kiné', 'Montpellier', ''],
        ['Claire Morin', 'Association Patients', 'Paris', ''],
        ['Jean-Paul Renaud', 'Bénévole', 'Toulouse', '']
    ];

    const stmt = db.prepare(`INSERT INTO candidates (name, profession, location, pdf_url) VALUES (?, ?, ?, ?)`);
    candidates.forEach(c => {
        stmt.run(c);
    });
    stmt.finalize();

    console.log("Database seeded with mock candidates and admin user.");
});
