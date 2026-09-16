import './bootstrap';
import './charts';

import Alpine from 'alpinejs';

// Served from this server as part of the build, not from a CDN.
//
// Every signed-in page hides its whole shell with x-cloak until Alpine starts,
// so when Alpine came from cdnjs a slow or filtered CDN — a campus network
// blocking it, or an outage — left every page of the system blank white.
// Bundled, Alpine arrives with the stylesheet or not at all.
window.Alpine = Alpine;
Alpine.start();
