import './bootstrap';
import Alpine from '@alpinejs/csp';
import { registerEnhancedSelects } from './lib/enhanced-selects';
import { registerFormBehaviors } from './lib/form-behaviors';
import { registerModalKit } from './lib/modal-kit';
import { registerPageTransitions } from './lib/page-transitions';
import { registerSearchHistory } from './lib/search-history';
import { registerAlpineStateCollections, registerThemeGlobals } from './lib/theme-state';
import './pages/account-settings-page';
import './pages/admin-dashboard-page';
import './pages/admin-reports-page';
import './pages/admin-ticket-show-page';
import './pages/admin-tickets-index-page';
import './pages/admin-users-create-page';
import './pages/admin-users-edit-page';
import './pages/admin-users-page';
import './pages/app-layout-notifications-page';
import './pages/client-dashboard-page';
import './pages/client-ticket-create-page';
import './pages/client-ticket-show-page';
import './pages/client-tickets-index-page';

registerThemeGlobals();

window.Alpine = Alpine;

registerAlpineStateCollections(Alpine);
Alpine.start();

registerPageTransitions();
registerFormBehaviors();
registerModalKit();
registerEnhancedSelects();
registerSearchHistory();
