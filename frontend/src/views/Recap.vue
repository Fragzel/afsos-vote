<template>
  <div class="afsos-shell">
    <div class="afsos-topbar">
      <div class="afsos-logo-zone">
        <img src="/logo.png" alt="AFSOS Logo" style="height: 40px;" />
        <div class="afsos-sep" style="background: var(--color-border-secondary);"></div>
        <div class="afsos-logo-label" style="color: var(--color-text-primary);">Élection du Conseil d'Administration</div>
      </div>
      <div class="afsos-secure">
        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        Vote sécurisé et anonyme
      </div>
    </div>
    
    <div class="afsos-main">
      <div class="afsos-steps" style="margin-bottom:20px">
        <div class="afsos-step done"><div class="afsos-step-dot">✓</div><span class="afsos-step-lbl">Éligibilité</span></div>
        <div class="afsos-step-line"></div>
        <div class="afsos-step done"><div class="afsos-step-dot">✓</div><span class="afsos-step-lbl">Vote</span></div>
        <div class="afsos-step-line"></div>
        <div class="afsos-step active"><div class="afsos-step-dot">3</div><span class="afsos-step-lbl">Récapitulatif</span></div>
        <div class="afsos-step-line"></div>
        <div class="afsos-step"><div class="afsos-step-dot">4</div><span class="afsos-step-lbl">Confirmation</span></div>
      </div>

      <div class="afsos-card">
        <h3 style="font-size: 15px; color: var(--color-text-primary); margin-bottom: 15px; display:flex; align-items:center; gap:8px;">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1a3a6b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
          Récapitulatif de votre bulletin
        </h3>

        <div style="background: var(--color-background-secondary); border-radius: 8px; padding: 15px; margin-bottom: 20px;">
          <div v-if="loading" style="color: var(--color-text-tertiary); font-size:13px;">Chargement...</div>
          
          <div v-else>
            <p style="font-size: 12px; color: var(--color-text-secondary); margin-bottom: 10px; font-weight: 500;">
              Vous avez sélectionné {{ selectedCandidates.length }} candidat(s) sur 5 maximum.
            </p>
            
            <div v-if="selectedCandidates.length === 0" style="font-style: italic; color: var(--color-text-secondary); font-size: 13px;">
              Vote blanc (aucun candidat sélectionné).
            </div>
            
            <ul v-else style="list-style: none;">
              <li v-for="c in fullSelectedDetails" :key="c.id" style="display:flex; align-items:center; gap:8px; font-size:13px; margin-bottom: 8px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#1D9E75" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <strong>{{ c.name }}</strong> <span style="color:var(--color-text-tertiary)">({{ c.profession }})</span>
              </li>
            </ul>
          </div>
        </div>

        <div style="background:#FEF0E7; border:1px solid #EF9F27; border-radius:8px; padding:12px; font-size:12px; color:#633806; margin-bottom:15px; display:flex; gap:8px; align-items:flex-start">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          Une fois validé, votre vote est définitif et ne peut pas être modifié. Il sera anonymisé.
        </div>

        <label style="display:flex; align-items:flex-start; gap:10px; padding:12px; border:1px solid var(--color-border-secondary); border-radius:8px; cursor:pointer; margin-bottom:20px" :style="agreed ? 'border-color:#1a3a6b; background:#f0f7ff;' : ''">
          <input type="checkbox" v-model="agreed" style="margin-top:2px;">
          <span style="font-size:12px; color:var(--color-text-secondary)">Je confirme mes choix et souhaite valider définitivement mon vote.</span>
        </label>

        <div v-if="error" style="color: #A32D2D; font-size: 13px; margin-bottom: 15px; text-align:center;">{{ error }}</div>

        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--color-border-tertiary); padding-top: 15px;">
          <button @click="router.push('/vote')" class="afsos-btn" style="background: transparent;">Retour pour modifier</button>
          <button @click="submitVote" class="afsos-btn afsos-btn-primary" :disabled="!agreed || submitting" :style="agreed ? 'background:#A32D2D; border-color:#A32D2D' : ''">
            {{ submitting ? 'Envoi...' : 'Valider définitivement' }}
          </button>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const selectedCandidates = ref([])
const fullSelectedDetails = ref([])
const loading = ref(true)
const agreed = ref(false)
const submitting = ref(false)
const error = ref('')

onMounted(async () => {
  const prev = sessionStorage.getItem('selectedCandidates')
  if (prev) {
    selectedCandidates.value = JSON.parse(prev)
  }

  // Fetch full details of candidates
  try {
    const res = await fetch('/api/candidates', {
      headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }
    })
    const data = await res.json()
    if (res.ok) {
      fullSelectedDetails.value = data.filter(c => selectedCandidates.value.includes(c.id))
    }
  } catch (err) {
    console.error(err)
  } finally {
    loading.value = false
  }
})

const submitVote = async () => {
  if (!agreed.value) return
  submitting.value = true
  error.value = ''

  try {
    const res = await fetch('/api/vote', {
      method: 'POST',
      headers: { 
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${localStorage.getItem('token')}` 
      },
      body: JSON.stringify({ candidateIds: selectedCandidates.value })
    })
    const data = await res.json()
    
    if (!res.ok) throw new Error(data.error || 'Erreur lors du vote')
    
    // Clear session storage
    sessionStorage.removeItem('selectedCandidates')
    
    // Update local user state
    const user = JSON.parse(localStorage.getItem('user'))
    user.has_voted = true
    localStorage.setItem('user', JSON.stringify(user))

    router.push('/confirmation')
  } catch (err) {
    error.value = err.message
  } finally {
    submitting.value = false
  }
}
</script>
