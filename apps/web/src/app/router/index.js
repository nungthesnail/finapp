import { createRouter, createWebHistory } from 'vue-router'
import { useAuth } from '../providers/auth-provider'

import PublicLayout from '../layout/public-layout.vue'
import AppLayout from '../layout/app-layout.vue'
import AdminLayout from '../layout/admin-layout.vue'

import LandingPage from '../../pages/public/landing-page.vue'
import PrivacyPage from '../../pages/public/privacy-page.vue'
import HelpPage from '../../pages/public/help-page.vue'
import LoginPage from '../../pages/auth/login-page.vue'
import RegisterPage from '../../pages/auth/register-page.vue'
import DashboardPage from '../../pages/app/dashboard-page.vue'
import AccountsPage from '../../pages/app/accounts-page.vue'
import TransactionsPage from '../../pages/app/transactions-page.vue'
import RecurringPage from '../../pages/app/recurring-page.vue'
import PlansPage from '../../pages/app/plans-page.vue'
import AnalyticsPage from '../../pages/app/analytics-page.vue'
import AiChatPage from '../../pages/app/ai-chat-page.vue'
import NotificationsPage from '../../pages/app/notifications-page.vue'
import SubscriptionPage from '../../pages/app/subscription-page.vue'
import SupportPage from '../../pages/app/support-page.vue'
import SettingsPage from '../../pages/app/settings-page.vue'
import AdminDashboardPage from '../../pages/admin/admin-dashboard-page.vue'
import AdminUsersPage from '../../pages/admin/admin-users-page.vue'
import AdminTariffsPage from '../../pages/admin/admin-tariffs-page.vue'
import AdminPaymentsPage from '../../pages/admin/admin-payments-page.vue'
import AdminAiModelsPage from '../../pages/admin/admin-ai-models-page.vue'
import AdminAuditPage from '../../pages/admin/admin-audit-page.vue'
import AdminSupportPage from '../../pages/admin/admin-support-page.vue'

const routes = [
  {
    path: '/',
    redirect: '/app',
  },
  {
    path: '/landing',
    component: PublicLayout,
    children: [{ path: '', name: 'landing', component: LandingPage }],
  },
  {
    path: '/privacy',
    component: PublicLayout,
    children: [{ path: '', name: 'privacy', component: PrivacyPage }],
  },
  {
    path: '/help',
    component: PublicLayout,
    children: [{ path: '', name: 'help', component: HelpPage }],
  },
  {
    path: '/auth',
    component: PublicLayout,
    meta: { guestOnly: true },
    children: [
      { path: 'login', name: 'auth-login', component: LoginPage, meta: { guestOnly: true } },
      { path: 'register', name: 'auth-register', component: RegisterPage, meta: { guestOnly: true } },
    ],
  },
  {
    path: '/app',
    component: AppLayout,
    meta: { requiresAuth: true },
    children: [
      { path: '', name: 'app-dashboard', component: DashboardPage },
      { path: 'accounts', name: 'app-accounts', component: AccountsPage },
      { path: 'transactions', name: 'app-transactions', component: TransactionsPage },
      { path: 'recurring', name: 'app-recurring', component: RecurringPage },
      { path: 'plans', name: 'app-plans', component: PlansPage },
      { path: 'analytics', name: 'app-analytics', component: AnalyticsPage },
      { path: 'ai', name: 'app-ai', component: AiChatPage },
      { path: 'notifications', name: 'app-notifications', component: NotificationsPage },
      { path: 'subscription', name: 'app-subscription', component: SubscriptionPage },
      { path: 'support', name: 'app-support', component: SupportPage },
      { path: 'settings', name: 'app-settings', component: SettingsPage },
    ],
  },
  {
    path: '/admin',
    component: AdminLayout,
    meta: { requiresAuth: true, requiresAdmin: true },
    children: [
      { path: '', name: 'admin-dashboard', component: AdminDashboardPage },
      { path: 'users', name: 'admin-users', component: AdminUsersPage },
      { path: 'tariffs', name: 'admin-tariffs', component: AdminTariffsPage },
      { path: 'payments', name: 'admin-payments', component: AdminPaymentsPage },
      { path: 'ai-models', name: 'admin-ai-models', component: AdminAiModelsPage },
      { path: 'audit', name: 'admin-audit', component: AdminAuditPage },
      { path: 'support', name: 'admin-support', component: AdminSupportPage },
    ],
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/app',
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach(async (to) => {
  const auth = useAuth()
  await auth.ensureInitialized()

  if ((to.meta.requiresAuth || to.meta.requiresAdmin) && !auth.state.user) {
    return { name: 'auth-login', query: { redirect: to.fullPath } }
  }

  if (to.meta.requiresAdmin && auth.state.user?.role !== 'ADMIN') {
    return { name: 'app-dashboard' }
  }

  if (to.meta.guestOnly && auth.state.user) {
    return { name: 'app-dashboard' }
  }

  return true
})

export default router
