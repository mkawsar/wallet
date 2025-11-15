import './bootstrap';
import '../css/app.css';
import { createApp } from 'vue';
import WalletApp from './components/WalletApp.vue';

const app = createApp(WalletApp);
app.mount('#app');
