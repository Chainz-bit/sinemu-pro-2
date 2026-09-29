import 'bootstrap/dist/css/bootstrap.min.css';
import '@fortawesome/fontawesome-free/css/all.min.css';
import 'flatpickr/dist/flatpickr.min.css';
import 'leaflet/dist/leaflet.css';
import '../../css/legacy/page-transition.css';
import '../../css/legacy/home.css';
import '../../css/legacy/flash-popup.css';
import '../../css/legacy/custom-select.css';
import '../../css/legacy/chatbot.css';

import 'bootstrap/dist/js/bootstrap.bundle.min.js';
import flatpickr from 'flatpickr';
import { Indonesian } from 'flatpickr/dist/l10n/id.js';
import * as L from 'leaflet';
import '../shared/page-transition.js';
import '../shared/flash-popup.js';
import '../shared/custom-select.js';
import '../apps/home/app.js';

flatpickr.localize(Indonesian);
window.flatpickr = flatpickr;
window.L = L;
