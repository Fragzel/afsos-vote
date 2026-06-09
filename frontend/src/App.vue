<template>
  <div class="app-container">
    <router-view></router-view>
  </div>
</template>

<style>
/* Base Styles matching AFSOS Mockup */
:root {
  --color-background-primary: #ffffff;
  --color-background-secondary: #f4f6f9;
  --color-border-primary: #1a3a6b;
  --color-border-secondary: #c9d3e0;
  --color-border-tertiary: #e2e8f0;
  --color-text-primary: #1e293b;
  --color-text-secondary: #475569;
  --color-text-tertiary: #94a3b8;
  --border-radius-lg: 12px;
  --border-radius-md: 8px;
  --font-sans: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
}

* { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: var(--font-sans);
  background-color: #e2e8f0; /* Darker background outside the shell */
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 100vh;
}

.app-container {
  width: 100%;
  max-width: 600px;
  margin: 20px auto;
}

/* Common Components */
.afsos-shell {
  background: var(--color-background-secondary);
  border-radius: var(--border-radius-lg);
  border: 0.5px solid var(--color-border-tertiary);
  overflow: hidden;
  box-shadow: 0 10px 25px rgba(0,0,0,0.05);
}

.afsos-topbar {
  background: #1a3a6b;
  padding: 0 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 60px;
}

.afsos-logo-zone {
  display: flex;
  align-items: center;
  gap: 14px;
}

.afsos-logo-svg {
  display: flex;
  align-items: center;
  gap: 3px;
}

.afsos-bubble {
  width: 30px;
  height: 30px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-weight: 500;
  color: #fff;
}

.afsos-sep {
  width: 0.5px;
  height: 28px;
  background: rgba(255,255,255,0.25);
  margin: 0 14px;
}

.afsos-logo-label {
  font-size: 12px;
  color: rgba(255,255,255,0.75);
  line-height: 1.4;
  max-width: 150px;
}

.afsos-secure {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 11px;
  color: rgba(255,255,255,0.6);
  border: 0.5px solid rgba(255,255,255,0.2);
  border-radius: var(--border-radius-md);
  padding: 5px 10px;
}

.afsos-main {
  padding: 24px 20px;
}

.afsos-card {
  background: var(--color-background-primary);
  border: 0.5px solid var(--color-border-tertiary);
  border-radius: var(--border-radius-lg);
  padding: 22px 22px;
  margin-bottom: 12px;
}

.afsos-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  padding: 10px 20px;
  border-radius: var(--border-radius-md);
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  border: 0.5px solid var(--color-border-secondary);
  background: var(--color-background-primary);
  color: var(--color-text-primary);
  transition: all 0.2s ease;
}

.afsos-btn:hover { background: #f8fafc; }

.afsos-btn-primary {
  background: #1a3a6b;
  border-color: #1a3a6b;
  color: #fff;
}
.afsos-btn-primary:hover { background: #132a4d; }

.afsos-btn-primary:disabled {
  background: #94a3b8;
  border-color: #94a3b8;
  cursor: not-allowed;
}

.form-group {
  margin-bottom: 15px;
  text-align: left;
}
.form-group label {
  display: block;
  font-size: 13px;
  margin-bottom: 5px;
  color: var(--color-text-secondary);
}
.form-group input {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid var(--color-border-secondary);
  border-radius: var(--border-radius-md);
  font-size: 14px;
}

.afsos-steps {
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 24px;
}

.afsos-step {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 5px;
  min-width: 70px;
}

.afsos-step-dot {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  border: 0.5px solid var(--color-border-secondary);
  background: var(--color-background-primary);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 500;
  color: var(--color-text-secondary);
}

.afsos-step.active .afsos-step-dot { background: #1a3a6b; border-color: #1a3a6b; color: #fff; }
.afsos-step.done .afsos-step-dot { background: #1D9E75; border-color: #1D9E75; color: #fff; }

.afsos-step-lbl { font-size: 10px; color: var(--color-text-tertiary); text-align: center; white-space: nowrap; }
.afsos-step.active .afsos-step-lbl { color: #1a3a6b; font-weight: 500; }
.afsos-step.done .afsos-step-lbl { color: #0F6E56; }

.afsos-step-line {
  flex: 1;
  height: 0.5px;
  background: var(--color-border-tertiary);
  min-width: 20px;
  max-width: 40px;
  margin-bottom: 16px;
}
.afsos-step.done ~ .afsos-step-line { background: #1D9E75; }

/* Admin Base Table Styles */
.admin-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.admin-table th, .admin-table td {
  padding: 10px;
  text-align: left;
  border-bottom: 1px solid var(--color-border-tertiary);
}
.admin-table th { color: var(--color-text-secondary); font-weight: 500; }

</style>
