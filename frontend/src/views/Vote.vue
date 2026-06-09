<template>
  <div class="afsos-shell">
    <div class="afsos-topbar" style="background: white; border-bottom: 1px solid var(--color-border-tertiary);">
      <div class="afsos-logo-zone">
        <img src="/logo.png" alt="AFSOS Logo" style="height: 40px;" />
        <div class="afsos-sep" style="background: var(--color-border-secondary);"></div>
        <div class="afsos-logo-label" style="color: var(--color-text-primary);">Élection du Conseil d'Administration</div>
      </div>
      <div style="display: flex; align-items: center; gap: 15px;">
        <div v-if="timeRemaining" style="font-size: 13px; color: #F57F17; font-weight: bold; background: #FFF9C4; padding: 4px 10px; border-radius: 4px; border: 1px solid #FBC02D;">
          ⏳ {{ timeRemaining }}
        </div>
        <button @click="logout" class="afsos-btn" style="padding: 5px 10px; font-size: 11px; border-color: transparent;">Déconnexion</button>
      </div>
    </div>
    
    <div class="afsos-main">
      <div class="afsos-steps" style="margin-bottom:20px">
        <div class="afsos-step done"><div class="afsos-step-dot">✓</div><span class="afsos-step-lbl">Éligibilité</span></div>
        <div class="afsos-step-line"></div>
        <div class="afsos-step active"><div class="afsos-step-dot">2</div><span class="afsos-step-lbl">Vote</span></div>
        <div class="afsos-step-line"></div>
        <div class="afsos-step"><div class="afsos-step-dot">3</div><span class="afsos-step-lbl">Récapitulatif</span></div>
        <div class="afsos-step-line"></div>
        <div class="afsos-step"><div class="afsos-step-dot">4</div><span class="afsos-step-lbl">Confirmation</span></div>
      </div>

      <div class="afsos-card" style="margin-bottom: 16px; background: #E6F0FA; border-color: #85B7EB;">
        <p style="font-size: 13px; color: #0C447C;">
          <strong>Règles du scrutin :</strong> Vous pouvez sélectionner jusqu'à <strong>{{ maxVotes }} candidats</strong> dans la liste ci-dessous. Vous pouvez également voter blanc.
        </p>
      </div>

      <div class="afsos-card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 15px;">
          <h3 style="font-size: 14px; color: var(--color-text-primary);">Candidats ({{ selectedCandidates.length }} / {{ maxVotes }} sélectionnés)</h3>
          <button @click="clearSelection" class="afsos-btn" style="padding: 5px 10px; font-size: 11px;">Vote blanc (Vider)</button>
        </div>

        <div v-if="loading" style="text-align:center; padding: 20px; color: var(--color-text-tertiary);">Chargement des candidats...</div>
        
        <div v-else style="display: grid; grid-template-columns: 1fr; gap: 10px;">
          <div v-for="c in candidates" :key="c.id" 
               class="candidat-card" 
               :class="{ selected: selectedCandidates.includes(c.id) }"
               @click="toggleSelection(c.id)">
            
            <div class="candidat-avatar">{{ c.name.charAt(0) }}</div>
            <div class="candidat-info">
              <div class="candidat-name">{{ c.name }}</div>
              <div class="candidat-role" style="text-align: center;">{{ c.profession }} – {{ c.location }}</div>
              <div style="display: flex; justify-content: center; margin-top: 6px;">
                <button v-if="c.pdf_url" @click.stop="viewPdf(c.pdf_url)" class="candidat-link" style="background:none; border:none; padding:0; cursor:pointer; display:flex; align-items:center; gap:4px;">
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                  Voir la fiche complète
                </button>
              </div>
            </div>
            <div class="candidat-check">
              <svg v-if="selectedCandidates.includes(c.id)" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
          </div>
        </div>

        <div v-if="error" style="color: #A32D2D; font-size: 12px; margin-top: 15px; text-align: center;">{{ error }}</div>

        <div style="display: flex; justify-content: flex-end; margin-top: 20px; padding-top: 15px; border-top: 1px solid var(--color-border-tertiary);">
          <button @click="goToRecap" class="afsos-btn afsos-btn-primary">
            Continuer vers le récapitulatif
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
          </button>
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
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const candidates = ref([])
const selectedCandidates = ref([])
const loading = ref(true)
const error = ref('')
const maxVotes = ref(5)

const showPdfModal = ref(false)
const currentPdfUrl = ref('')

const timeRemaining = ref('')
let countdownInterval = null

onMounted(async () => {
  try {
    const resPub = await fetch('/api/public-settings')
    const dataPub = await resPub.json()
    if (dataPub.election_end_date) {
      const updateCountdown = () => {
        const endDate = new Date(dataPub.election_end_date)
        const now = new Date()
        const diff = endDate - now
        if (diff <= 0) {
          timeRemaining.value = "L'élection est clôturée."
          if (countdownInterval) clearInterval(countdownInterval)
        } else {
          const d = Math.floor(diff / (1000 * 60 * 60 * 24))
          const h = Math.floor((diff / (1000 * 60 * 60)) % 24)
          const m = Math.floor((diff / 1000 / 60) % 60)
          const s = Math.floor((diff / 1000) % 60)
          timeRemaining.value = `Fin du scrutin dans : ${d}j ${h}h ${m}m ${s}s`
        }
      }
      updateCountdown()
      countdownInterval = setInterval(updateCountdown, 1000)
    }

    const resSettings = await fetch('/api/settings', {
      headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }
    })
    const dataSettings = await resSettings.json()
    if (dataSettings.max_votes) maxVotes.value = parseInt(dataSettings.max_votes, 10)
    
    // Load previous selection if exists
    const prev = sessionStorage.getItem('selectedCandidates')
    if (prev) selectedCandidates.value = JSON.parse(prev)

    const res = await fetch('/api/candidates', {
      headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }
    })
    
    if (res.status === 401 || res.status === 403) {
      localStorage.removeItem('token')
      router.push('/login')
      return
    }

    const data = await res.json()
    if (!res.ok) throw new Error(data.error)
    candidates.value = data
  } catch (err) {
    error.value = "Erreur de chargement des candidats."
  } finally {
    loading.value = false
  }
})

const toggleSelection = (id) => {
  if (selectedCandidates.value.includes(id)) {
    selectedCandidates.value = selectedCandidates.value.filter(cId => cId !== id)
  } else {
    if (selectedCandidates.value.length < maxVotes.value) {
      selectedCandidates.value.push(id)
    } else {
      error.value = `Vous ne pouvez sélectionner que ${maxVotes.value} candidats maximum.`
      setTimeout(() => error.value = '', 3000)
    }
  }
}

const clearSelection = () => {
  selectedCandidates.value = []
}

const goToRecap = () => {
  sessionStorage.setItem('selectedCandidates', JSON.stringify(selectedCandidates.value))
  router.push('/recap')
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
.candidat-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  border: 1px solid var(--color-border-tertiary);
  border-radius: var(--border-radius-md);
  cursor: pointer;
  transition: all 0.2s;
  background: var(--color-background-primary);
}
.candidat-card:hover { border-color: var(--color-border-secondary); }
.candidat-card.selected {
  border-color: #1a3a6b;
  background: #f0f7ff;
}
.candidat-avatar {
  width: 40px; height: 40px; border-radius: 50%;
  background: #e2e8f0; color: #475569;
  display: flex; align-items: center; justify-content: center;
  font-weight: 600; font-size: 14px;
}
.candidat-card.selected .candidat-avatar {
  background: #1a3a6b; color: #fff;
}
.candidat-info { flex: 1; text-align: center; }
.candidat-name { font-size: 14px; font-weight: 600; color: var(--color-text-primary); margin-bottom: 2px;}
.candidat-role { font-size: 12px; color: var(--color-text-secondary); margin-bottom: 4px; }
.candidat-link { font-size: 11px; color: #1a3a6b; text-decoration: none; }
.candidat-link:hover { text-decoration: underline; }
.candidat-check {
  width: 20px; height: 20px; border-radius: 50%;
  border: 1px solid var(--color-border-secondary);
  display: flex; align-items: center; justify-content: center;
  color: #fff;
}
.candidat-card.selected .candidat-check {
  background: #1a3a6b; border-color: #1a3a6b;
}

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
