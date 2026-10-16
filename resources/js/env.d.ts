/// <reference types="vite/client" />

declare global {
  interface Window {
    __TLINK__?: {
      auth: {
        isAuthenticated: boolean;
        user?: {
          id: number;
          name: string;
          email: string;
        };
      };
      feed: Array<{
        id: number;
        content: string;
        type: string;
        visibility: string;
        published_at: string | null;
        author?: {
          id: number;
          name: string;
        };
      }>;
      entities: Array<{
        id: number;
        name: string;
        slug: string;
      }>;
      governorates: Array<{
        id: number;
        name: string;
        slug: string;
      }>;
      csrf: string;
    };
  }
}

export {};
