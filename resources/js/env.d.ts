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
        sentiment: string;
        published_at: string | null;
        author?: {
          id: number;
          name: string;
          headline?: string | null;
          avatar_url?: string | null;
        };
        attachments: Array<{
          url: string;
          mime_type: string;
          kind: 'image' | 'video';
        }>;
        counts: {
          comments: number;
          reactions: number;
          shares: number;
          views: number;
        };
        reactions: Record<string, number>;
        comments_preview: Array<{
          id: number;
          content: string;
          author: string;
          author_avatar_url?: string | null;
          created_at: string | null;
          replies: Array<{
            id: number;
            content: string;
            author: string;
            author_avatar_url?: string | null;
            created_at: string | null;
          }>;
        }>;
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
