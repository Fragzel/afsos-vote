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
        <div class="afsos-step done"><div class="afsos-step-dot">✓</div><span class="afsos-step-lbl">Récapitulatif</span></div>
        <div class="afsos-step-line"></div>
        <div class="afsos-step active"><div class="afsos-step-dot">4</div><span class="afsos-step-lbl">Confirmation</span></div>
      </div>

      <div class="afsos-card" style="text-align:center; padding: 40px 20px;">
        <div style="width: 60px; height: 60px; border-radius: 50%; background: #E1F5EE; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; color: #0F6E56;">
          <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <h2 style="font-size: 18px; font-weight: 500; color: var(--color-text-primary); margin-bottom: 10px;">Votre vote a été enregistré</h2>
        <p style="font-size: 13px; color: var(--color-text-secondary); line-height: 1.6; max-width: 340px; margin: 0 auto 20px;">
          Votre vote est anonyme et sécurisé. Il a été dissocié de votre identifiant personnel. Merci pour votre participation à l'élection du Conseil d'Administration de l'AFSOS.
        </p>
        <div style="display:inline-flex;align-items:center;gap:6px;background:var(--color-background-secondary);border:1px solid var(--color-border-tertiary);border-radius:var(--border-radius-md);padding:8px 16px;font-size:12px;color:var(--color-text-secondary)">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          Enregistré le {{ new Date().toLocaleDateString('fr-FR') }} à {{ new Date().toLocaleTimeString('fr-FR', {hour: '2-digit', minute:'2-digit'}) }}
        </div>
        
        <div style="margin-top: 30px;">
          <button @click="logout" class="afsos-btn" style="font-size:12px;">Se déconnecter</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router'

const router = useRouter()

const logout = () => {
  localStorage.removeItem('token')
  localStorage.removeItem('user')
  router.push('/login')
}
</script>
