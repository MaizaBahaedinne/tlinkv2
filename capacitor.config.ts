import type { CapacitorConfig } from '@capacitor/cli';

/**
 * Plusieurs applications mobiles à partir d'un seul dépôt : une par sous-domaine (voir docs/subdomains.md).
 * L'application à construire se choisit avec la variable CAP_APP (link par défaut) :
 *   CAP_APP=store npx cap sync
 * Chaque application a ses propres projets natifs dans mobile/<app>/android et mobile/<app>/ios.
 * CAP_DOMAIN permet de viser un autre domaine (préproduction par exemple).
 */
const domain = process.env.CAP_DOMAIN ?? 'tunivisionsfoundation.org';

const apps = {
  link: { appId: 'com.tunivisions.tlink', appName: 'T.Link', url: `https://link.${domain}` },
  store: { appId: 'com.tunivisions.stores', appName: 'Tunivisions Stores', url: `https://store.${domain}/boutique` },
  academy: { appId: 'com.tunivisions.academy', appName: 'Tunivisions Academy', url: `https://academy.${domain}/academy` },
  messenger: { appId: 'com.tunivisions.messenger', appName: 'T.Link Messenger', url: `https://messenger.${domain}/messagerie` },
} as const;

type AppKey = keyof typeof apps;

const key = (process.env.CAP_APP ?? 'link') as AppKey;
const app = apps[key];

if (!app) {
  throw new Error(`CAP_APP inconnu « ${key} » : utilisez ${Object.keys(apps).join(', ')}.`);
}

const config: CapacitorConfig = {
  appId: app.appId,
  appName: app.appName,
  // Page de secours (hors connexion) : l'application elle-même est servie par le site en HTTPS.
  webDir: 'mobile/www',
  server: {
    url: app.url,
    cleartext: false,
    // Applications cloisonnées : chacune ne navigue que sur son propre hôte (donné par server.url). Seule exception, l'hôte T.Link, où vit la connexion
    // (retour de Google) ; tout autre lien s'ouvre dans le navigateur du téléphone.
    allowNavigation: [`link.${domain}`],
  },
  android: { path: `mobile/${key}/android` },
  ios: { path: `mobile/${key}/ios` },
};

export default config;
