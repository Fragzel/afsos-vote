const express = require('express');
const cors = require('cors');
const multer = require('multer');
const db = require('./db');
const path = require('path');
const jwt = require('jsonwebtoken');
const fs = require('fs');
const csv = require('csv-parser');
const nodemailer = require('nodemailer');
require('dotenv').config();

const app = express();
const port = process.env.PORT || 3000;
const SECRET_KEY = process.env.SECRET_KEY || 'afsos_super_secret_key_change_me';

// Setup Nodemailer Transporter
const transporter = nodemailer.createTransport({
    host: process.env.SMTP_HOST || 'smtp.example.com',
    port: process.env.SMTP_PORT || 587,
    secure: process.env.SMTP_PORT == 465, // true for 465, false for other ports
    auth: {
        user: process.env.SMTP_USER,
        pass: process.env.SMTP_PASS
    }
});

// In-memory email queue to respect SMTP rate limits (e.g. OVH limits to 200/hour)
let emailQueue = [];
let isProcessingQueue = false;
const EMAIL_DELAY_MS = parseInt(process.env.EMAIL_DELAY_MS) || 20000; // 20 seconds default

const processNextEmail = () => {
    if (emailQueue.length === 0) {
        isProcessingQueue = false;
        console.log("File d'attente d'emails vide. Traitement terminé.");
        return;
    }

    isProcessingQueue = true;
    const task = emailQueue.shift();
    
    const hasSmtp = process.env.SMTP_HOST && process.env.SMTP_USER;

    if (hasSmtp) {
        transporter.sendMail(task.mailOptions, (error, info) => {
            if (error) {
                console.error(`Erreur d'envoi d'email à ${task.email}:`, error);
            } else {
                console.log(`Email envoyé avec succès à ${task.email}`);
                db.run(`UPDATE users SET reminder_sent = CURRENT_TIMESTAMP WHERE id = ?`, [task.userId]);
            }
            setTimeout(processNextEmail, EMAIL_DELAY_MS);
        });
    } else {
        console.warn(`Mode Démo - Email simulé pour ${task.email}`);
        db.run(`UPDATE users SET reminder_sent = CURRENT_TIMESTAMP WHERE id = ?`, [task.userId]);
        setTimeout(processNextEmail, EMAIL_DELAY_MS);
    }
};

const addToEmailQueue = (userId, email, mailOptions) => {
    if (!emailQueue.some(item => item.userId === userId)) {
        emailQueue.push({ userId, email, mailOptions });
        console.log(`Ajouté à la file d'attente d'emails : ${email}`);
    }

    if (!isProcessingQueue) {
        processNextEmail();
    }
};

app.use(cors());
app.use(express.json());

// Multer Setup for CSV Uploads
const upload = multer({ dest: 'uploads/' });

// Multer Setup for PDF Uploads
const storagePdf = multer.diskStorage({
  destination: function (req, file, cb) {
    const pdfDir = path.join(__dirname, 'uploads', 'pdfs');
    if (!fs.existsSync(pdfDir)){
        fs.mkdirSync(pdfDir, { recursive: true });
    }
    cb(null, pdfDir)
  },
  filename: function (req, file, cb) {
    cb(null, Date.now() + '-' + file.originalname.replace(/[^a-zA-Z0-9.]/g, '_'))
  }
})
const uploadPdf = multer({ storage: storagePdf });

app.use('/uploads/pdfs', express.static(path.join(__dirname, 'uploads', 'pdfs')));

// Middleware to verify JWT token
const authenticateToken = (req, res, next) => {
    const authHeader = req.headers['authorization'];
    const token = authHeader && authHeader.split(' ')[1];
    if (!token) return res.sendStatus(401);

    jwt.verify(token, SECRET_KEY, (err, user) => {
        if (err) return res.sendStatus(403);
        req.user = user;
        next();
    });
};

// Health Check Endpoint
app.get('/api/health', (req, res) => {
    res.json({ status: 'AFSOS Vote API is running' });
});

// Public Settings Endpoint
app.get('/api/public-settings', (req, res) => {
    db.all(`SELECT key, value FROM settings`, (err, rows) => {
        if (err) return res.status(500).json({ error: err.message });
        const settings = {};
        rows.forEach(r => settings[r.key] = r.value);
        res.json({ election_end_date: settings.election_end_date, max_votes: settings.max_votes });
    });
});

// Settings Endpoint (Admin)
app.get('/api/settings', authenticateToken, (req, res) => {
    db.all(`SELECT key, value FROM settings`, (err, rows) => {
        if (err) return res.status(500).json({ error: err.message });
        const settings = {};
        rows.forEach(r => settings[r.key] = r.value);
        res.json(settings);
    });
});

// 1. Auth Endpoint: Request Code
app.post('/api/request-code', (req, res) => {
    const { email } = req.body;
    
    db.get(`SELECT * FROM users WHERE email = ?`, [email], (err, user) => {
        if (err) return res.status(500).json({ error: err.message });
        
        const isAdmin = email === 'admin@afsos.org';
        
        if (!user && !isAdmin) {
            return res.status(404).json({ error: "not_found" });
        }

        db.get(`SELECT value FROM settings WHERE key = 'election_end_date'`, (err, row) => {
            if (!err && row && row.value && !isAdmin) {
                const endDate = new Date(row.value);
                if (new Date() > endDate) {
                    return res.status(403).json({ error: "election_closed" });
                }
            }

            // Generate a 6-digit code
            const code = Math.floor(100000 + Math.random() * 900000).toString();
            const expires = new Date(Date.now() + 15 * 60000); // 15 mins

        console.log(`CODE DE CONNEXION POUR ${email} : ${code}`);

        // Préparation de l'email
        const mailOptions = {
            from: process.env.SMTP_FROM || '"AFSOS" <ne-pas-repondre@afsos.org>',
            to: email,
            subject: 'Votre code de connexion sécurisé - Élection du CA AFSOS',
            html: `
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
                <div style="background-color: #1a3a6b; padding: 20px; text-align: center;">
                    <h2 style="color: #ffffff; margin: 0; font-size: 20px;">Élection du Conseil d'Administration</h2>
                </div>
                <div style="padding: 30px; background-color: #ffffff; color: #1e293b;">
                    <p style="font-size: 16px; margin-bottom: 20px;">Bonjour,</p>
                    <p style="font-size: 15px; line-height: 1.5; margin-bottom: 30px;">
                        Vous avez demandé à vous connecter au portail de vote sécurisé de l'AFSOS. 
                        Veuillez utiliser le code de sécurité à 6 chiffres ci-dessous pour finaliser votre connexion :
                    </p>
                    <div style="text-align: center; margin-bottom: 30px;">
                        <span style="display: inline-block; background-color: #f4f6f9; border: 1px solid #c9d3e0; border-radius: 6px; padding: 15px 30px; font-size: 28px; font-weight: bold; letter-spacing: 5px; color: #1a3a6b;">
                            ${code}
                        </span>
                    </div>
                    <p style="font-size: 13px; color: #475569; margin-bottom: 10px;">
                        <em>Ce code est valide pendant 15 minutes. Ne le partagez avec personne.</em>
                    </p>
                    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;" />
                    <p style="font-size: 12px; color: #94a3b8; text-align: center;">
                        Ceci est un email automatique, merci de ne pas y répondre.
                    </p>
                </div>
            </div>
            `
        };

        const saveAndSend = (userId) => {
            let query = userId ? 
                `UPDATE users SET login_code = ?, login_code_expires = ? WHERE id = ?` : 
                `UPDATE users SET login_code = ?, login_code_expires = ? WHERE email = ?`;
            
            let params = [code, expires.toISOString(), userId || email];

            db.run(query, params, (err) => {
                if (err) return res.status(500).json({ error: err.message });
                
                // Si les identifiants SMTP sont configurés, on envoie le mail
                if (process.env.SMTP_HOST && process.env.SMTP_USER) {
                    transporter.sendMail(mailOptions, (error, info) => {
                        if (error) {
                            console.error("Erreur d'envoi d'email :", error);
                            return res.status(500).json({ error: "Erreur lors de l'envoi de l'email." });
                        }
                        res.json({ success: true, message: "Code envoyé par email" });
                    });
                } else {
                    // Fallback en développement si pas de SMTP
                    console.warn("ATTENTION: SMTP non configuré dans .env. L'email n'a pas été envoyé.");
                    res.json({ success: true, message: "Code généré (voir console du serveur)", code: code });
                }
            });
        };

        if (isAdmin && !user) {
                saveAndSend(null);
            } else {
                saveAndSend(user.id);
            }
        });
    });
});

// 1b. Auth Endpoint: Verify Code
app.post('/api/verify-code', (req, res) => {
    const { email, code } = req.body;
    
    db.get(`SELECT * FROM users WHERE email = ?`, [email], (err, user) => {
        if (err) return res.status(500).json({ error: err.message });
        if (!user) return res.status(401).json({ error: "Utilisateur introuvable" });
        
        const isAdmin = email === 'admin@afsos.org';
        const isStaticAdminCode = isAdmin && code === '199719';

        if (!isStaticAdminCode) {
            const now = new Date();
            const expires = new Date(user.login_code_expires);

            if (user.login_code !== code || now > expires) {
                return res.status(401).json({ error: "Code invalide ou expiré." });
            }
            
            // Clear the code
            db.run(`UPDATE users SET login_code = NULL, login_code_expires = NULL WHERE id = ?`, [user.id]);
        }
        const token = jwt.sign({ 
            id: user.id, 
            email, 
            isAdmin 
        }, SECRET_KEY, { expiresIn: '2h' });
        
        res.json({ 
            token, 
            user: { 
                email, 
                has_voted: user.has_voted,
                isAdmin
            } 
        });
    });
});

// 2. Get Candidates
app.get('/api/candidates', authenticateToken, (req, res) => {
    db.all(`SELECT * FROM candidates`, [], (err, rows) => {
        if (err) return res.status(500).json({ error: err.message });
        res.json(rows);
    });
});

// 3. Submit Vote
app.post('/api/vote', authenticateToken, (req, res) => {
    const { candidateIds } = req.body; // Array of IDs, can be empty for white vote
    const userId = req.user.id;

    if (candidateIds && candidateIds.length > 5) {
        return res.status(400).json({ error: "Vous ne pouvez voter que pour 5 candidats maximum." });
    }

    db.get(`SELECT value FROM settings WHERE key = 'election_end_date'`, (errSetting, row) => {
        if (!errSetting && row && row.value) {
            const endDate = new Date(row.value);
            if (new Date() > endDate) {
                return res.status(403).json({ error: "L'élection est clôturée. Vous ne pouvez plus voter." });
            }
        }

        db.get(`SELECT has_voted FROM users WHERE id = ?`, [userId], (err, user) => {
        if (err || !user) return res.status(500).json({ error: "Erreur utilisateur" });
        if (user.has_voted) return res.status(400).json({ error: "Vous avez déjà voté." });

        db.serialize(() => {
            db.run('BEGIN TRANSACTION');

            // Insert votes without linking user
            const stmt = db.prepare(`INSERT INTO votes (candidate_id) VALUES (?)`);
            if (candidateIds && candidateIds.length > 0) {
                candidateIds.forEach(id => {
                    stmt.run(id);
                });
            } else {
                // White vote (candidate_id = NULL)
                stmt.run(null);
            }
            stmt.finalize();

            // Mark user as voted
            db.run(`UPDATE users SET has_voted = 1 WHERE id = ?`, [userId], (updateErr) => {
                if (updateErr) {
                    db.run('ROLLBACK');
                    return res.status(500).json({ error: "Erreur lors de l'enregistrement" });
                }
                db.run('COMMIT', (commitErr) => {
                    if (commitErr) {
                        return res.status(500).json({ error: "Erreur lors de la validation du vote" });
                    }
                    
                    res.json({ success: true, message: "Vote enregistré avec succès" });

                    // Send confirmation email in background
                    const voterEmail = req.user.email;
                    if (voterEmail && voterEmail !== 'admin@afsos.org') {
                        const confirmationMailOptions = {
                            from: process.env.SMTP_FROM || '"AFSOS" <ne-pas-repondre@afsos.org>',
                            to: voterEmail,
                            subject: 'Confirmation de votre vote - Élection du CA AFSOS',
                            html: `
                            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
                                <div style="background-color: #1a3a6b; padding: 20px; text-align: center;">
                                    <h2 style="color: #ffffff; margin: 0; font-size: 20px;">Élection du Conseil d'Administration</h2>
                                </div>
                                <div style="padding: 30px; background-color: #ffffff; color: #1e293b;">
                                    <p style="font-size: 16px; margin-bottom: 20px;">Bonjour,</p>
                                    <p style="font-size: 15px; line-height: 1.5; margin-bottom: 20px;">
                                        Nous vous confirmons que votre vote pour l'élection du Conseil d'Administration de l'AFSOS a bien été enregistré avec succès.
                                    </p>
                                    <p style="font-size: 15px; line-height: 1.5; margin-bottom: 30px;">
                                        Conformément aux principes de confidentialité et d'anonymat de notre système de vote électronique, le détail de vos choix reste strictement secret et n'est pas lié à votre identité.
                                    </p>
                                    <p style="font-size: 14px; color: #475569; margin-bottom: 10px;">
                                        Merci pour votre participation active.
                                    </p>
                                    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;" />
                                    <p style="font-size: 12px; color: #94a3b8; text-align: center;">
                                        Ceci est un email automatique, merci de ne pas y répondre.
                                    </p>
                                </div>
                            </div>
                            `
                        };

                        if (process.env.SMTP_HOST && process.env.SMTP_USER) {
                            transporter.sendMail(confirmationMailOptions, (mailErr, info) => {
                                if (mailErr) {
                                    console.error("Erreur lors de l'envoi de l'email de confirmation de vote :", mailErr);
                                } else {
                                    console.log("Email de confirmation de vote envoyé à :", voterEmail);
                                }
                            });
                        } else {
                            console.log("Mode Démo - Email de confirmation de vote simulé pour :", voterEmail);
                        }
                    }
                });
            });
            });
        });
    });
});

// 4. Admin: Upload CSV
app.post('/api/admin/upload-csv', authenticateToken, upload.single('file'), (req, res) => {
    if (!req.user.isAdmin) return res.status(403).json({ error: "Accès refusé" });
    
    const results = [];
    fs.createReadStream(req.file.path)
      .pipe(csv())
      .on('data', (data) => {
          // Check for standard 'email' or the specific 'E-mail utilisateur' from Restrict Content Pro
          const userEmail = data['E-mail utilisateur'] || data.email;
          if(userEmail) results.push(userEmail.trim());
      })
      .on('end', () => {
          db.serialize(() => {
              db.run('BEGIN TRANSACTION');
              
              if (results.length > 0) {
                  const placeholders = results.map(() => '?').join(',');
                  db.run(`DELETE FROM users WHERE email != 'admin@afsos.org' AND email NOT IN (${placeholders})`, results);
              }

              const stmt = db.prepare(`INSERT OR IGNORE INTO users (email) VALUES (?)`);
              results.forEach(email => stmt.run(email));
              stmt.finalize();
              
              db.run('COMMIT', (err) => {
                  fs.unlinkSync(req.file.path);
                  if (err) return res.status(500).json({ error: err.message });
                  res.json({ success: true, count: results.length });
              });
          });
      });
});

// 5. Admin: Stats
app.get('/api/admin/stats', authenticateToken, (req, res) => {
    if (!req.user.isAdmin) return res.status(403).json({ error: "Accès refusé" });

    db.get(`SELECT COUNT(*) as totalEligible, SUM(has_voted) as totalVoted FROM users WHERE email != 'admin@afsos.org'`, (err, row) => {
        if (err) return res.status(500).json({ error: err.message });
        
        db.all(`SELECT candidate_id, COUNT(*) as vote_count FROM votes GROUP BY candidate_id`, (err2, votes) => {
            if (err2) return res.status(500).json({ error: err2.message });
            res.json({ stats: row, results: votes });
        });
    });
});

// 5b. Admin: Remind all (Queued)
app.post('/api/admin/remind-all', authenticateToken, (req, res) => {
    if (!req.user.isAdmin) return res.status(403).json({ error: "Accès refusé" });

    db.all(`SELECT id, email FROM users WHERE has_voted = 0 AND email != 'admin@afsos.org'`, [], (err, users) => {
        if (err) return res.status(500).json({ error: err.message });
        
        if (users.length === 0) {
            return res.json({ success: true, message: "Aucun utilisateur à inviter." });
        }

        const voteLink = process.env.VOTE_LINK || 'http://localhost:5173';

        users.forEach(u => {
            const mailOptions = {
                from: process.env.SMTP_FROM || '"AFSOS" <ne-pas-repondre@afsos.org>',
                to: u.email,
                subject: 'Élection du Conseil d\'Administration AFSOS - Ouverture du scrutin',
                html: `
                    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
                        <div style="background-color: #1a3a6b; padding: 20px; text-align: center;">
                            <h2 style="color: #ffffff; margin: 0; font-size: 20px;">Élection du Conseil d'Administration</h2>
                        </div>
                        <div style="padding: 30px; background-color: #ffffff; color: #1e293b;">
                            <p style="font-size: 15px; line-height: 1.5; margin-bottom: 20px;">
                                Cher membre AFSOS,<br/><br/>
                                L'élection au vote du Conseil d'Administration AFSOS est désormais ouverte.
                            </p>
                            <p style="font-size: 15px; line-height: 1.5; margin-bottom: 30px;">
                                Pour participer au vote, veuillez vous rendre sur la plateforme sécurisée en cliquant sur le bouton ci-dessous :
                            </p>
                            <div style="text-align: center; margin-bottom: 30px;">
                                <a href="${voteLink}" style="display: inline-block; background-color: #1a3a6b; color: #ffffff; text-decoration: none; padding: 12px 25px; border-radius: 6px; font-weight: bold;">
                                    Accéder au portail de vote
                                </a>
                            </div>
                            <p style="font-size: 13px; color: #64748b; margin-bottom: 20px;">
                                Si le bouton ci-dessus ne fonctionne pas, vous pouvez copier et coller ce lien dans votre navigateur :<br/>
                                <a href="${voteLink}" style="color: #1a3a6b;">${voteLink}</a>
                            </p>
                            <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;" />
                            <p style="font-size: 12px; color: #94a3b8; text-align: center;">
                                Ceci est un email automatique, merci de ne pas y répondre.
                            </p>
                        </div>
                    </div>
                `
            };
            addToEmailQueue(u.id, u.email, mailOptions);
        });
        
        res.json({ success: true, message: `${users.length} invitations ajoutées à la file d'attente d'envoi. (1 mail toutes les ${EMAIL_DELAY_MS/1000}s)` });
    });
});

// 5c. Admin: Remind individual user (Queued)
app.post('/api/admin/remind-user', authenticateToken, (req, res) => {
    if (!req.user.isAdmin) return res.status(403).json({ error: "Accès refusé" });

    const { userId } = req.body;
    if (!userId) return res.status(400).json({ error: "ID utilisateur requis" });

    db.get(`SELECT id, email, has_voted FROM users WHERE id = ? AND email != 'admin@afsos.org'`, [userId], (err, user) => {
        if (err) return res.status(500).json({ error: err.message });
        if (!user) return res.status(404).json({ error: "Utilisateur introuvable" });
        if (user.has_voted) return res.status(400).json({ error: "L'utilisateur a déjà voté" });

        const voteLink = process.env.VOTE_LINK || 'http://localhost:5173';

        const mailOptions = {
            from: process.env.SMTP_FROM || '"AFSOS" <ne-pas-repondre@afsos.org>',
            to: user.email,
            subject: 'Élection du Conseil d\'Administration AFSOS - Ouverture du scrutin',
            html: `
                <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
                    <div style="background-color: #1a3a6b; padding: 20px; text-align: center;">
                        <h2 style="color: #ffffff; margin: 0; font-size: 20px;">Élection du Conseil d'Administration</h2>
                    </div>
                    <div style="padding: 30px; background-color: #ffffff; color: #1e293b;">
                        <p style="font-size: 15px; line-height: 1.5; margin-bottom: 20px;">
                            Cher membre AFSOS,<br/><br/>
                            L'élection au vote du Conseil d'Administration AFSOS est désormais ouverte.
                        </p>
                        <p style="font-size: 15px; line-height: 1.5; margin-bottom: 30px;">
                            Pour participer au vote, veuillez vous rendre sur la plateforme sécurisée en cliquant sur le bouton ci-dessous :
                        </p>
                        <div style="text-align: center; margin-bottom: 30px;">
                            <a href="${voteLink}" style="display: inline-block; background-color: #1a3a6b; color: #ffffff; text-decoration: none; padding: 12px 25px; border-radius: 6px; font-weight: bold;">
                                Accéder au portail de vote
                            </a>
                        </div>
                        <p style="font-size: 13px; color: #64748b; margin-bottom: 20px;">
                            Si le bouton ci-dessus ne fonctionne pas, vous pouvez copier et coller ce lien dans votre navigateur :<br/>
                            <a href="${voteLink}" style="color: #1a3a6b;">${voteLink}</a>
                        </p>
                        <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;" />
                        <p style="font-size: 12px; color: #94a3b8; text-align: center;">
                            Ceci est un email automatique, merci de ne pas y répondre.
                        </p>
                    </div>
                </div>
            `
        };

        addToEmailQueue(user.id, user.email, mailOptions);
        
        res.json({ success: true, message: `Invitation pour ${user.email} ajoutée à la file d'attente d'envoi.` });
    });
});

// 5d. Admin: Get Email Queue Status
app.get('/api/admin/queue-status', authenticateToken, (req, res) => {
    if (!req.user.isAdmin) return res.status(403).json({ error: "Accès refusé" });
    res.json({
        queueLength: emailQueue.length,
        isProcessing: isProcessingQueue,
        delayMs: EMAIL_DELAY_MS
    });
});

// 6. Admin: Get Users
app.get('/api/admin/users', authenticateToken, (req, res) => {
    if (!req.user.isAdmin) return res.status(403).json({ error: "Accès refusé" });
    
    db.all(`SELECT id, email, has_voted, reminder_sent FROM users WHERE email != 'admin@afsos.org'`, [], (err, rows) => {
        if (err) return res.status(500).json({ error: err.message });
        res.json(rows);
    });
});

// 7. Admin: Export Results to CSV
app.get('/api/admin/export-results', authenticateToken, (req, res) => {
    if (!req.user.isAdmin) return res.status(403).json({ error: "Accès refusé" });

    db.all(`
        SELECT c.name as Candidat, c.profession as Profession, COUNT(v.id) as Voix
        FROM candidates c
        LEFT JOIN votes v ON c.id = v.candidate_id
        GROUP BY c.id
        ORDER BY Voix DESC
    `, (err, results) => {
        if (err) return res.status(500).json({ error: err.message });
        
        let csvContent = "Candidat,Profession,Voix\n";
        results.forEach(row => {
            csvContent += `"${row.Candidat}","${row.Profession}",${row.Voix}\n`;
        });
        
        // Add white votes (if we want to track them separately, or if candidate_id is null)
        db.get(`SELECT COUNT(*) as Voix FROM votes WHERE candidate_id IS NULL`, (err, whiteVotes) => {
            if (!err && whiteVotes && whiteVotes.Voix > 0) {
                csvContent += `"VOTE BLANC","",${whiteVotes.Voix}\n`;
            }
            res.setHeader('Content-Type', 'text/csv');
            res.setHeader('Content-Disposition', 'attachment; filename="resultats_afsos_vote.csv"');
            res.send(csvContent);
        });
    });
});

// 8. Admin: Reset Vote
app.post('/api/admin/reset-vote', authenticateToken, (req, res) => {
    if (!req.user.isAdmin) return res.status(403).json({ error: "Accès refusé" });

    db.serialize(() => {
        db.run('BEGIN TRANSACTION');
        db.run(`UPDATE users SET has_voted = 0, reminder_sent = NULL`);
        db.run(`DELETE FROM votes`);
        db.run('COMMIT', (err) => {
            if (err) return res.status(500).json({ error: err.message });
            res.json({ success: true, message: "Élection réinitialisée avec succès." });
        });
    });
});

// 9. Admin: Candidates CRUD
app.post('/api/admin/candidates', authenticateToken, uploadPdf.single('pdf'), (req, res) => {
    if (!req.user.isAdmin) return res.status(403).json({ error: "Accès refusé" });

    const { name, profession, location } = req.body;
    let pdf_url = '';
    
    if (req.file) {
        pdf_url = `/uploads/pdfs/${req.file.filename}`;
    }

    db.run(`INSERT INTO candidates (name, profession, location, pdf_url) VALUES (?, ?, ?, ?)`, 
        [name, profession, location, pdf_url], 
        function(err) {
            if (err) return res.status(500).json({ error: err.message });
            res.json({ success: true, id: this.lastID });
    });
});

app.delete('/api/admin/candidates/:id', authenticateToken, (req, res) => {
    if (!req.user.isAdmin) return res.status(403).json({ error: "Accès refusé" });

    const candidateId = req.params.id;
    
    db.get(`SELECT pdf_url FROM candidates WHERE id = ?`, [candidateId], (err, candidate) => {
        if (!err && candidate && candidate.pdf_url) {
            const filename = candidate.pdf_url.split('/').pop();
            const filePath = path.join(__dirname, 'uploads', 'pdfs', filename);
            if (fs.existsSync(filePath)) {
                fs.unlinkSync(filePath);
            }
        }
        
        db.serialize(() => {
            db.run('BEGIN TRANSACTION');
            db.run(`DELETE FROM votes WHERE candidate_id = ?`, [candidateId]);
            db.run(`DELETE FROM candidates WHERE id = ?`, [candidateId]);
            db.run('COMMIT', (err) => {
                if (err) return res.status(500).json({ error: err.message });
                res.json({ success: true, message: "Candidat supprimé." });
            });
        });
    });
});

// 10. Admin: Update Settings
app.post('/api/admin/settings', authenticateToken, (req, res) => {
    if (!req.user.isAdmin) return res.status(403).json({ error: "Accès refusé" });

    const { max_votes, election_end_date } = req.body;
    db.serialize(() => {
        db.run('BEGIN TRANSACTION');
        if (max_votes) {
            db.run(`UPDATE settings SET value = ? WHERE key = 'max_votes'`, [max_votes.toString()]);
        }
        if (election_end_date) {
            db.run(`UPDATE settings SET value = ? WHERE key = 'election_end_date'`, [election_end_date]);
        }
        db.run('COMMIT', (err) => {
            if (err) return res.status(500).json({ error: err.message });
            res.json({ success: true });
        });
    });
});

// Serve Frontend Static Files
app.use(express.static(path.join(__dirname, '../frontend/dist')));

// Fallback to index.html for Single Page Application routing (Vue Router)
app.get('/*splat', (req, res) => {
    if (!req.path.startsWith('/api') && !req.path.startsWith('/uploads')) {
        res.sendFile(path.join(__dirname, '../frontend/dist', 'index.html'));
    } else {
        res.status(404).json({ error: 'Not Found' });
    }
});

app.listen(port, () => {
    console.log(`Server listening on port ${port}`);
});
