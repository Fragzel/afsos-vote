<template>
  <div class="afsos-shell" style="max-width: 800px; margin: 20px auto;">
    <div class="afsos-topbar">
      <div class="afsos-logo-zone">
        <img src="/logo.png" alt="AFSOS Logo" style="height: 40px;" />
        <div class="afsos-sep"></div>
        <div class="afsos-logo-label">Administration AFSOS Vote</div>
      </div>
      <div style="display: flex; align-items: center; gap: 15px;">
        <div v-if="timeRemaining" style="font-size: 13px; color: #F57F17; font-weight: bold; background: #FFF9C4; padding: 4px 10px; border-radius: 4px; border: 1px solid #FBC02D;">
          ⏳ {{ timeRemaining }}
        </div>
        <button @click="logout" class="afsos-btn" style="padding: 5px 10px; font-size: 11px; border-color: transparent; background: transparent;">Déconnexion</button>
      </div>
    </div>
    
    <div class="afsos-main">
      <h2 style="font-size: 18px; margin-bottom: 20px;">Tableau de bord</h2>
      
      <div v-if="loading" style="text-align: center; color: var(--color-text-tertiary);">Chargement...</div>
      
      <div v-else>
        <!-- Queue status info banner if emails are sending -->
        <div v-if="queueStatus && queueStatus.queueLength > 0" style="background: #E0F2FE; border: 1px solid #7DD3FC; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; font-size: 13px;">
          <div style="display: flex; align-items: center; gap: 10px; color: #0369A1;">
            <span>⏳ <strong>Envoi en cours :</strong> {{ queueStatus.queueLength }} invitation(s) restante(s) dans la file d'attente (1 envoi toutes les {{ queueStatus.delayMs / 1000 }}s).</span>
          </div>
          <span style="font-size: 11px; color: #0284C7; font-weight: bold; background: #BAE6FD; padding: 2px 8px; border-radius: 12px;">Actif</span>
        </div>

        <!-- Stats -->
        <div style="display: flex; gap: 15px; margin-bottom: 30px;">
          <div class="afsos-card" style="flex: 1; text-align: center;">
            <div style="font-size: 24px; font-weight: bold; color: #1a3a6b;">{{ stats.totalEligible || 0 }}</div>
            <div style="font-size: 12px; color: var(--color-text-secondary);">Membres éligibles</div>
          </div>
          <div class="afsos-card" style="flex: 1; text-align: center;">
            <div style="font-size: 24px; font-weight: bold; color: #1D9E75;">{{ stats.totalVoted || 0 }}</div>
            <div style="font-size: 12px; color: var(--color-text-secondary);">Ont voté</div>
          </div>
          <div class="afsos-card" style="flex: 1; text-align: center;">
            <div style="font-size: 24px; font-weight: bold; color: #E05A1E;">{{ (stats.totalEligible || 0) - (stats.totalVoted || 0) }}</div>
            <div style="font-size: 12px; color: var(--color-text-secondary);">En attente</div>
          </div>
        </div>

        <!-- Import CSV -->
        <div class="afsos-card" style="margin-bottom: 30px;">
          <h3 style="font-size: 15px; margin-bottom: 10px;">Importer la liste électorale (CSV)</h3>
          <p style="font-size: 12px; color: var(--color-text-secondary); margin-bottom: 10px;">Attention: Importer un nouveau fichier remplacera l'ancienne liste d'électeurs. (Les administrateurs et les statuts des votes sont conservés).</p>
          <div style="display: flex; gap: 10px; align-items: center;">
            <input type="file" @change="handleFileUpload" accept=".csv" style="font-size: 13px;">
            <button @click="uploadCSV" class="afsos-btn afsos-btn-primary" :disabled="!csvFile || uploading">
              {{ uploading ? 'Import...' : 'Importer' }}
            </button>
          </div>
          <div v-if="uploadMsg" style="font-size: 12px; color: #1D9E75; margin-top: 10px;">{{ uploadMsg }}</div>
        </div>

        <!-- Gestion des Candidats -->
        <div class="afsos-card" style="margin-bottom: 30px;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h3 style="font-size: 15px; margin: 0;">Candidats à l'élection</h3>
            <button @click="showCandidateForm = !showCandidateForm" class="afsos-btn afsos-btn-primary" style="padding: 6px 12px; font-size: 12px; border-radius: 20px;">
              {{ showCandidateForm ? 'Fermer le formulaire ✕' : '+ Ajouter un candidat' }}
            </button>
          </div>
          
          <div v-if="showCandidateForm" style="background: #F8FAFC; padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #cbd5e1; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);">
            <h4 style="font-size: 14px; margin-bottom: 15px; margin-top: 0; color: #1a3a6b;">Nouveau candidat</h4>
            
            <div style="display: flex; flex-direction: column; gap: 15px;">
              <div style="display: flex; gap: 15px;">
                <div style="flex: 1;">
                  <label style="display: block; font-size: 12px; margin-bottom: 5px; color: var(--color-text-secondary); font-weight: 500;">Prénom et Nom *</label>
                  <input type="text" v-model="newCandidate.name" placeholder="ex: Dr. Marie Dupont" class="form-group input" style="margin: 0; padding: 8px; font-size: 13px;">
                </div>
                <div style="flex: 1;">
                  <label style="display: block; font-size: 12px; margin-bottom: 5px; color: var(--color-text-secondary); font-weight: 500;">Profession *</label>
                  <input type="text" v-model="newCandidate.profession" placeholder="ex: Oncologue médicale" class="form-group input" style="margin: 0; padding: 8px; font-size: 13px;">
                </div>
              </div>
              
              <div style="display: flex; gap: 15px;">
                <div style="flex: 1;">
                  <label style="display: block; font-size: 12px; margin-bottom: 5px; color: var(--color-text-secondary); font-weight: 500;">Établissement / Ville *</label>
                  <input type="text" v-model="newCandidate.location" placeholder="ex: CHU de Bordeaux" class="form-group input" style="margin: 0; padding: 8px; font-size: 13px;">
                </div>
                <div style="flex: 1;">
                  <label style="display: block; font-size: 12px; margin-bottom: 5px; color: var(--color-text-secondary); font-weight: 500;">Fiche de présentation (PDF optionnel)</label>
                  <input type="file" @change="e => newCandidate.pdf = e.target.files[0]" accept=".pdf" style="font-size: 13px; padding: 4px; border: 1px dashed #94a3b8; border-radius: 4px; width: 100%; background: white;">
                </div>
              </div>
            </div>
            
            <div style="margin-top: 15px; text-align: right; border-top: 1px solid #e2e8f0; padding-top: 15px;">
              <button @click="addCandidate" class="afsos-btn afsos-btn-primary" style="padding: 8px 20px; font-size: 13px;" :disabled="!newCandidate.name || !newCandidate.profession || uploadingCandidate">
                {{ uploadingCandidate ? 'Enregistrement...' : 'Enregistrer le candidat' }}
              </button>
            </div>
          </div>

          <table class="admin-table">
            <thead><tr><th>Nom</th><th>Profession</th><th>Voix actuelles</th><th>Action</th></tr></thead>
            <tbody>
              <tr v-for="c in candidates" :key="c.id">
                <td>
                  {{ c.name }} 
                  <button v-if="c.pdf_url" @click.stop="viewPdf(c.pdf_url)" style="background:none; border:none; padding:0; cursor:pointer; font-size: 10px; color: #1a3a6b; text-decoration: underline;">(Voir PDF)</button>
                </td>
                <td>{{ c.profession }}</td>
                <td style="font-weight: bold;">{{ getCandidateVotes(c.id) }}</td>
                <td><button @click="deleteCandidate(c.id)" class="afsos-btn" style="padding: 4px 8px; font-size: 11px; color: #A32D2D;">Supprimer</button></td>
              </tr>
              <tr v-if="candidates.length === 0"><td colspan="4" style="text-align: center; color: #94a3b8;">Aucun candidat.</td></tr>
            </tbody>
          </table>
          <div style="text-align: right; margin-top: 15px;">
            <button @click="exportCSV" class="afsos-btn afsos-btn-primary" style="padding: 6px 12px; font-size: 12px;">Exporter les résultats (CSV)</button>
          </div>
        </div>

        <!-- Réglages & Réinitialisation -->
        <div class="afsos-card" style="margin-bottom: 30px; border-left: 4px solid #1a3a6b;">
          <h3 style="font-size: 15px; margin-bottom: 10px;">Réglages de l'élection</h3>
          <div style="display: flex; gap: 20px; align-items: center; justify-content: space-between; flex-wrap: wrap;">
            <div style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
              <div style="display: flex; gap: 10px; align-items: center;">
                <label style="font-size: 13px;">Nombre max. de votes :</label>
                <input type="number" v-model="settings.max_votes" @change="saveSettings" style="width: 60px; padding: 5px; border-radius: 4px; border: 1px solid #ccc;">
              </div>
              <div style="display: flex; gap: 10px; align-items: center;">
                <label style="font-size: 13px;">Date de fin du vote :</label>
                <input type="datetime-local" v-model="settings.election_end_date" @change="saveSettings" style="padding: 5px; border-radius: 4px; border: 1px solid #ccc; font-size: 13px;">
              </div>
              <span v-if="settingMsg" style="color: #1D9E75; font-size: 12px;">{{ settingMsg }}</span>
            </div>
            <div>
              <button @click="resetElection" class="afsos-btn" style="background: #FFF0F0; color: #A32D2D; border: 1px solid #F09595; padding: 6px 12px; font-size: 12px;">
                Réinitialiser l'élection (Nouveau Vote)
              </button>
            </div>
          </div>
        </div>

        <!-- Users List -->
        <div class="afsos-card">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <h3 style="font-size: 15px; margin: 0;">Liste des électeurs ({{ filteredUsers.length }})</h3>
            <div style="display: flex; gap: 10px; align-items: center;">
              <input type="text" v-model="searchEmail" placeholder="Rechercher un email..." style="padding: 5px; font-size: 12px; border-radius: 4px; border: 1px solid #cbd5e1;">
              <button @click="remindAll" class="afsos-btn afsos-btn-primary" :disabled="reminding" style="padding: 5px 12px; font-size: 11px; width: auto;">
                {{ reminding ? 'Envoi...' : "Envoyer l'invitation à tous" }}
              </button>
            </div>
          </div>
          <div v-if="remindMsg" style="font-size: 12px; color: #1D9E75; margin-bottom: 10px; text-align: right;">{{ remindMsg }}</div>
          <div style="overflow-x: auto; max-height: 400px; border: 1px solid #f1f5f9;">
            <table class="admin-table">
              <thead style="position: sticky; top: 0; background: white; z-index: 1;">
                <tr>
                  <th>Email</th>
                  <th>Statut</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="u in filteredUsers" :key="u.id">
                  <td>{{ u.email }}</td>
                  <td>
                    <span v-if="u.has_voted" style="color: #1D9E75; font-weight: 500;">A voté</span>
                    <span v-else style="color: #E05A1E;">En attente</span>
                  </td>
                  <td>
                    <button 
                      v-if="!u.has_voted" 
                      @click="remindUser(u)" 
                      class="afsos-btn afsos-btn-primary" 
                      style="padding: 3px 8px; font-size: 10px; width: auto; background-color: #1a3a6b; color: white;"
                      :disabled="remindingUsers[u.id]"
                    >
                      {{ remindingUsers[u.id] ? 'Envoi...' : 'Envoyer le lien' }}
                    </button>
                    <span v-else style="color: var(--color-text-tertiary); font-size: 11px;">-</span>
                  </td>
                </tr>
                <tr v-if="filteredUsers.length === 0">
                  <td colspan="3" style="text-align: center; color: var(--color-text-tertiary);">Aucun utilisateur trouvé.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- PDF Modal Overlay -->
    <div v-if="showPdfModal" class="pdf-modal-overlay" @click="closePdf">
      <div class="pdf-modal-content" @click.stop>
        <div style="display:flex; justify-content:space-between; align-items:center; padding: 15px 20px; border-bottom: 1px solid var(--color-border-secondary); background: white;">
          <h3 style="margin: 0; font-size: 15px; color: var(--color-text-primary);">Fiche de présentation</h3>
          <button @click="closePdf" class="afsos-btn" style="padding: 5px 15px; font-size: 12px; border-color: transparent;">Fermer ✕</button>
        </div>
        <div style="flex: 1; background: #e2e8f0;">
          <iframe :src="currentPdfUrl" width="100%" height="100%" frameborder="0" style="display: block;"></iframe>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const stats = ref({ totalEligible: 0, totalVoted: 0 })
const results = ref([])
const candidates = ref([])
const users = ref([])
const loading = ref(true)

const settings = ref({ max_votes: 5 })
const settingMsg = ref('')

const searchEmail = ref('')
const filteredUsers = computed(() => {
  if (!searchEmail.value) return users.value;
  return users.value.filter(u => u.email.toLowerCase().includes(searchEmail.value.toLowerCase()))
})

const showCandidateForm = ref(true)
const newCandidate = ref({ name: '', profession: '', location: '', pdf: null })
const uploadingCandidate = ref(false)

const csvFile = ref(null)
const uploading = ref(false)
const uploadMsg = ref('')

const reminding = ref(false)
const remindMsg = ref('')
const remindingUsers = ref({})

const showPdfModal = ref(false)
const currentPdfUrl = ref('')

const timeRemaining = ref('')
let countdownInterval = null

const queueStatus = ref(null)
let queueInterval = null

const getAuthHeaders = () => ({ 'Authorization': `Bearer ${localStorage.getItem('token')}` })

onMounted(async () => {
  await fetchData()
  await fetchQueueStatus()
  queueInterval = setInterval(fetchQueueStatus, 4000)
  countdownInterval = setInterval(() => {
    if (!settings.value.election_end_date) return;
    const endDate = new Date(settings.value.election_end_date)
    const now = new Date()
    const diff = endDate - now
    if (diff <= 0) {
      timeRemaining.value = "L'élection est clôturée."
    } else {
      const d = Math.floor(diff / (1000 * 60 * 60 * 24))
      const h = Math.floor((diff / (1000 * 60 * 60)) % 24)
      const m = Math.floor((diff / 1000 / 60) % 60)
      const s = Math.floor((diff / 1000) % 60)
      timeRemaining.value = `Fin du scrutin dans : ${d}j ${h}h ${m}m ${s}s`
    }
  }, 1000)
})

onUnmounted(() => {
  if (countdownInterval) clearInterval(countdownInterval)
  if (queueInterval) clearInterval(queueInterval)
})

const fetchQueueStatus = async () => {
  try {
    const res = await fetch('/api/admin/queue-status', { headers: getAuthHeaders() })
    if (res.ok) {
      queueStatus.value = await res.json()
    }
  } catch(e) {
    console.error("Erreur de récupération du statut de la file d'attente", e)
  }
}

const fetchData = async () => {
  loading.value = true
  try {
    const resSettings = await fetch('/api/settings', { headers: getAuthHeaders() })
    if (resSettings.status === 401 || resSettings.status === 403) return logout()
    settings.value = await resSettings.json()

    const resCandidates = await fetch('/api/candidates', { headers: getAuthHeaders() })
    candidates.value = await resCandidates.json()

    await refreshData()
  } catch(err) {
    console.error(err)
  } finally {
    loading.value = false
  }
}

const refreshData = async () => {
  try {
    const resStats = await fetch('/api/admin/stats', { headers: getAuthHeaders() })
    const dataStats = await resStats.json()
    if(dataStats.stats) stats.value = dataStats.stats
    if(dataStats.results) results.value = dataStats.results

    const resUsers = await fetch('/api/admin/users', { headers: getAuthHeaders() })
    users.value = await resUsers.json() || []
  } catch(err) {
    console.error(err)
  }
}

const getCandidateVotes = (id) => {
  const c = results.value.find(r => r.candidate_id === id)
  return c ? c.vote_count : 0
}

const saveSettings = async () => {
  settingMsg.value = '...'
  try {
    await fetch('/api/admin/settings', {
      method: 'POST',
      headers: { ...getAuthHeaders(), 'Content-Type': 'application/json' },
      body: JSON.stringify({ max_votes: settings.value.max_votes, election_end_date: settings.value.election_end_date })
    })
    settingMsg.value = 'Sauvegardé'
    setTimeout(() => settingMsg.value = '', 2000)
  } catch(e) {}
}

const addCandidate = async () => {
  uploadingCandidate.value = true;
  const formData = new FormData();
  formData.append('name', newCandidate.value.name);
  formData.append('profession', newCandidate.value.profession);
  formData.append('location', newCandidate.value.location);
  if (newCandidate.value.pdf) formData.append('pdf', newCandidate.value.pdf);

  await fetch('/api/admin/candidates', {
    method: 'POST',
    headers: getAuthHeaders(),
    body: formData
  })
  
  newCandidate.value = { name: '', profession: '', location: '', pdf: null }
  uploadingCandidate.value = false;
  
  // Reload candidates list specifically
  const resCandidates = await fetch('/api/candidates', { headers: getAuthHeaders() })
  candidates.value = await resCandidates.json()
  
  await refreshData()
}

const deleteCandidate = async (id) => {
  if (!confirm("Voulez-vous vraiment supprimer ce candidat ? (Cela effacera les votes liés à lui)")) return;
  await fetch(`/api/admin/candidates/${id}`, {
    method: 'DELETE',
    headers: getAuthHeaders()
  })
  
  const resCandidates = await fetch('/api/candidates', { headers: getAuthHeaders() })
  candidates.value = await resCandidates.json()
  
  await refreshData()
}

const exportCSV = async () => {
    const res = await fetch('/api/admin/export-results', { headers: getAuthHeaders() })
    const blob = await res.blob()
    const url = window.URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = "resultats_afsos_vote.csv"
    a.click()
}

const resetElection = async () => {
  const conf1 = confirm("ATTENTION ! Cette action va effacer définitivement tous les votes enregistrés. Les compteurs retomberont à zéro. Voulez-vous continuer ?");
  if (!conf1) return;
  const conf2 = confirm("Êtes-vous ABSOLUMENT SÛR ? C'est irréversible.");
  if (!conf2) return;

  try {
    await fetch('/api/admin/reset-vote', {
      method: 'POST',
      headers: getAuthHeaders()
    });
    alert("Élection réinitialisée.");
    await refreshData();
  } catch(e) {
    alert("Erreur lors de la réinitialisation.");
  }
}

const remindAll = async () => {
  if (!confirm("Voulez-vous envoyer l'e-mail d'invitation à voter à toutes les personnes n'ayant pas encore voté ?")) return;
  reminding.value = true;
  remindMsg.value = '';
  try {
    const res = await fetch('/api/admin/remind-all', {
      method: 'POST',
      headers: getAuthHeaders()
    })
    const data = await res.json()
    remindMsg.value = data.message || "Invitations envoyées"
    await fetchQueueStatus()
  } catch(err) {
    remindMsg.value = "Erreur lors de l'envoi."
  } finally {
    reminding.value = false
  }
}

const remindUser = async (user) => {
  if (!confirm(`Voulez-vous envoyer un e-mail avec le lien de vote à ${user.email} ?`)) return;
  remindingUsers.value[user.id] = true
  try {
    const res = await fetch('/api/admin/remind-user', {
      method: 'POST',
      headers: { ...getAuthHeaders(), 'Content-Type': 'application/json' },
      body: JSON.stringify({ userId: user.id })
    })
    const data = await res.json()
    if (res.ok) {
      alert(data.message || `Lien de vote envoyé avec succès à ${user.email}`)
      await fetchQueueStatus()
      await refreshData()
    } else {
      alert(data.error || "Erreur lors de l'envoi.")
    }
  } catch (err) {
    console.error(err)
    alert("Erreur réseau lors de l'envoi.")
  } finally {
    remindingUsers.value[user.id] = false
  }
}

const handleFileUpload = (e) => { csvFile.value = e.target.files[0] }

const uploadCSV = async () => {
  if(!csvFile.value) return
  uploading.value = true
  uploadMsg.value = ''
  const formData = new FormData()
  formData.append('file', csvFile.value)
  try {
    const res = await fetch('/api/admin/upload-csv', {
      method: 'POST',
      headers: getAuthHeaders(),
      body: formData
    })
    const data = await res.json()
    if(res.ok) {
      uploadMsg.value = `${data.count} emails ajoutés/traités.`
      await refreshData()
    }
  } catch(err) {
    uploadMsg.value = "Erreur d'import."
  } finally {
    uploading.value = false
  }
}

const logout = () => {
  localStorage.removeItem('token')
  localStorage.removeItem('user')
  router.push('/login')
}

const viewPdf = (url) => {
  currentPdfUrl.value = url;
  showPdfModal.value = true;
}

const closePdf = () => {
  showPdfModal.value = false;
  currentPdfUrl.value = '';
}
</script>

<style scoped>
.pdf-modal-overlay {
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(15, 23, 42, 0.7);
  backdrop-filter: blur(4px);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}
.pdf-modal-content {
  background: white;
  width: 100%;
  max-width: 900px;
  height: 90vh;
  border-radius: 8px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}
</style>
