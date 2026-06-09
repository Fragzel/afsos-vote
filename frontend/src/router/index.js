import { createRouter, createWebHistory } from 'vue-router'
import Login from '../views/Login.vue'
import Vote from '../views/Vote.vue'
import Recap from '../views/Recap.vue'
import Confirmation from '../views/Confirmation.vue'
import AdminDashboard from '../views/AdminDashboard.vue'

const routes = [
  { path: '/', redirect: '/login' },
  { path: '/login', name: 'Login', component: Login },
  { path: '/vote', name: 'Vote', component: Vote, meta: { requiresAuth: true } },
  { path: '/recap', name: 'Recap', component: Recap, meta: { requiresAuth: true } },
  { path: '/confirmation', name: 'Confirmation', component: Confirmation, meta: { requiresAuth: true } },
  { path: '/admin', name: 'AdminDashboard', component: AdminDashboard, meta: { requiresAuth: true, requiresAdmin: true } }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

router.beforeEach((to, from, next) => {
  const token = localStorage.getItem('token')
  const userStr = localStorage.getItem('user')
  const user = userStr ? JSON.parse(userStr) : {}
  
  if (to.meta.requiresAuth && !token) {
    next({ name: 'Login' })
  } else if (to.meta.requiresAdmin && !user.isAdmin) {
    next({ name: 'Vote' })
  } else {
    next()
  }
})

export default router
