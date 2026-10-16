import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'com.tunivisions.tlink',
  appName: 'T.Link',
  webDir: 'public/build',
  server: {
    cleartext: false,
  },
};

export default config;
