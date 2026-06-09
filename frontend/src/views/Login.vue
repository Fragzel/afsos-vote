<template>
  <div class="afsos-shell">
    <div class="afsos-topbar">
      <div class="afsos-logo-zone">
        <img src="/logo.png" alt="AFSOS Logo" style="height: 40px;" />
        <div class="afsos-sep"></div>
        <div class="afsos-logo-label">Élection du Conseil d'Administration</div>
      </div>
    </div>
    <div class="afsos-main">
      <div class="afsos-card" style="text-align: center; padding: 40px 20px;">
        <div style="width:50px;height:50px;border-radius:50%;background:#E6F0FA;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;color:#1a3a6b;font-size:22px">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
        </div>
        <h2 style="font-size:18px; color:var(--color-text-primary); margin-bottom: 8px;">Connexion au portail de vote</h2>
        
        <div v-if="timeRemaining" style="background: #FFF9C4; border: 1px solid #FBC02D; padding: 10px; border-radius: 4px; margin-bottom: 20px; text-align: center; color: #F57F17; font-weight: bold; font-size: 14px;">
          ⏳ {{ timeRemaining }}
        </div>

        <div v-if="isElectionClosed" style="text-align: center; color: #A32D2D; font-size: 16px; padding: 20px; background: #FCEBEB; border: 1px solid #F09595; border-radius: 8px;">
          L'élection est désormais clôturée. Merci de votre participation.
        </div>
        
        <div v-else-if="!stepCode && !notEligible">
          <p style="font-size:13px; color:var(--color-text-secondary); margin-bottom: 24px;">Entrez votre adresse email pour recevoir votre code de connexion sécurisé.</p>
          <form @submit.prevent="requestCode" style="max-width: 300px; margin: 0 auto;">
            <div class="form-group">
              <label>Adresse Email</label>
              <input type="email" v-model="email" required placeholder="jean.dupont@email.com">
            </div>
            <div v-if="error" style="color: #A32D2D; font-size: 13px; margin-bottom: 15px; background: #FCEBEB; padding: 10px; border-radius: 8px; border: 1px solid #F09595;">
              {{ error }}
            </div>
            <button type="submit" class="afsos-btn afsos-btn-primary" :disabled="loading" style="width: 100%; margin-top: 10px;">
              {{ loading ? 'Vérification...' : 'Recevoir le code pour voter' }}
            </button>
          </form>
        </div>

        <div v-else-if="notEligible" style="max-width: 400px; margin: 0 auto; text-align: center;">
          <div style="background: #FCEBEB; border: 1px solid #F09595; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
            <h3 style="color: #A32D2D; font-size: 15px; margin-bottom: 8px;">Adresse email non reconnue</h3>
            <p style="color: #791F1F; font-size: 13px;">Il semblerait que votre adhésion ne soit pas à jour ou que cet email ne figure pas dans la liste de nos membres actifs.</p>
          </div>
          <p style="font-size: 13px; color: var(--color-text-secondary); margin-bottom: 20px;">
            Pour participer au vote, votre cotisation doit être à jour.
          </p>
          <a href="https://www.afsos.org/decouvrir-lafsos/devenir-membre-afsos/" target="_blank" style="text-decoration: none;">
            <button class="afsos-btn afsos-btn-primary" style="width: 100%; margin-bottom: 10px;">Renouveler mon adhésion</button>
          </a>
          <button @click="notEligible = false; email = ''" class="afsos-btn" style="width: 100%; background: transparent;">Rentrer un autre mail</button>
        </div>

        <div v-else>
          <p style="font-size:13px; color:var(--color-text-secondary); margin-bottom: 24px;">Un code à 6 chiffres a été envoyé à <strong>{{ email }}</strong>.<br/>(Regardez dans la console du serveur pour la démo)</p>
          <div v-if="devCode" style="background: #E6F0FA; border: 1px solid #1a3a6b; padding: 10px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; color: #1a3a6b; text-align: center;">
            💡 Code généré (Mode Démo) : <span style="font-size: 16px; letter-spacing: 2px;">{{ devCode }}</span>
          </div>
          <form @submit.prevent="verifyCode" style="max-width: 300px; margin: 0 auto;">
            <div class="form-group">
              <label>Code de sécurité (6 chiffres)</label>
              <input type="text" v-model="code" required placeholder="123456" style="text-align: center; letter-spacing: 5px; font-size: 18px;">
            </div>
            <div v-if="error" style="color: #A32D2D; font-size: 13px; margin-bottom: 15px; background: #FCEBEB; padding: 10px; border-radius: 8px; border: 1px solid #F09595;">
              {{ error }}
            </div>
            <button type="submit" class="afsos-btn afsos-btn-primary" :disabled="loading" style="width: 100%; margin-top: 10px;">
              {{ loading ? 'Connexion...' : 'Se connecter' }}
            </button>
            <button type="button" @click="stepCode = false" class="afsos-btn" style="width: 100%; margin-top: 10px; background: transparent; border: none;">
              Annuler
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const email = ref('')
const code = ref('')
const error = ref('')
const loading = ref(false)
const stepCode = ref(false)
const devCode = ref('')
const notEligible = ref(false)
const timeRemaining = ref('')
const isElectionClosed = ref(false)
let countdownInterval = null

onMounted(async () => {
  localStorage.removeItem('token')
  localStorage.removeItem('user')

  try {
    const res = await fetch('/api/public-settings')
    const data = await res.json()
    if (data.election_end_date) {
      const updateCountdown = () => {
        const endDate = new Date(data.election_end_date)
        const now = new Date()
        const diff = endDate - now
        
        if (diff <= 0) {
          isElectionClosed.value = true
          timeRemaining.value = ""
          if (countdownInterval) clearInterval(countdownInterval)
        } else {
          isElectionClosed.value = false
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
  } catch(e) {}
})

const requestCode = async () => {
  error.value = ''
  loading.value = true
  notEligible.value = false
  try {
    const res = await fetch('/api/request-code', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: email.value })
    })
    const data = await res.json()
    
    if (res.status === 403 && data.error === 'election_closed') {
      isElectionClosed.value = true
      return
    }

    if (res.status === 404 || data.error === 'not_found') {
      notEligible.value = true
      return
    }

    if (!res.ok) throw new Error(data.error || 'Erreur')
    
    devCode.value = data.code || ''
    stepCode.value = true
  } catch (err) {
    error.value = err.message
  } finally {
    loading.value = false
  }
}

const verifyCode = async () => {
  error.value = ''
  loading.value = true
  try {
    const res = await fetch('/api/verify-code', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: email.value, code: code.value })
    })
    const data = await res.json()
    if (!res.ok) throw new Error(data.error || 'Code invalide')
    
    localStorage.setItem('token', data.token)
    localStorage.setItem('user', JSON.stringify(data.user))
    
    if (data.user.isAdmin) {
      router.push('/admin')
    } else if (data.user.has_voted) {
      error.value = "Vous avez déjà voté."
    } else {
      router.push('/vote')
    }
  } catch (err) {
    error.value = err.message
  } finally {
    loading.value = false
  }
}
</script>
