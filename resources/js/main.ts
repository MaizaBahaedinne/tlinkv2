import { createApp } from 'vue';
import { createPinia } from 'pinia';
import '@fortawesome/fontawesome-free/css/all.min.css';
import App from './ui/App.vue';

createApp(App).use(createPinia()).mount('#tlink-app');
