import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './ui/App.vue';

createApp(App).use(createPinia()).mount('#tlink-app');
