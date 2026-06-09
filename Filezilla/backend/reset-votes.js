const db = require('./db');

db.serialize(() => {
    db.run("UPDATE users SET has_voted = 0", (err) => {
        if (err) console.error("Erreur lors de la réinitialisation des utilisateurs:", err);
    });
    db.run("DELETE FROM votes", (err) => {
        if (err) console.error("Erreur lors de la suppression des votes:", err);
    });
    console.log("Les votes et le statut des utilisateurs ont été réinitialisés avec succès !");
});
