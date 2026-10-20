// React domain modules import these ports, never @capacitor/* directly.
// Contract reference. Android/iOS adapters require their own integration tests.
export type Capability = 'available' | 'unsupported' | 'denied';
export type LocalFile = {
  name: string;
  mime: string;
  bytes: number;
  source: Blob | { uri: string }; // stream native files; avoid whole-file base64 copies
  dispose(): Promise<void>;
};
export interface CameraPort {
  capability(): Promise<Capability>;
  capture(): Promise<LocalFile | null>; // null is user cancellation
}
export interface FilesPort {
  pick(accept: readonly string[], maxBytes: number): Promise<LocalFile[]>;
  upload(file: LocalFile, intent: {url: string; headers: Record<string,string>}): Promise<void>;
}
export interface NotificationsPort {
  capability(): Promise<Capability>;
  register(): Promise<{kind:'web_push'; endpoint:string; p256dh:string; auth:string} |
    {kind:'apns'|'fcm'; token:string} | null>;
  unregister(): Promise<void>;
}
export interface SharePort {
  share(input: {title:string; url?:string; files?:LocalFile[]}): Promise<'shared'|'cancelled'|'unsupported'>;
}
export interface LinksPort {
  subscribe(callback: (canonicalPath: string) => void): () => void;
  // Accept only app-owned HTTPS hosts and known paths; hold route until login.
  // Opening a link never confers authorization, including on cold launch.
}
export interface SessionPort {
  applyAuth(headers: Headers): Promise<void>;
  // Web: same-origin HttpOnly/Secure cookie + CSRF header, fetch credentials.
  // Native: bearer token from OS-backed store, never localStorage/Preferences.
  clear(): Promise<void>;
  // Also clear in-memory API cache, local thumbnails and pending notifications.
}
export interface SecureTokenStore {
  read(): Promise<string | null>;
  write(token: string): Promise<void>;
  delete(): Promise<void>;
  // iOS Keychain; Android encrypted token blob with a key in Android Keystore.
  // This is NOT implemented by @capacitor/preferences.
}
export interface PlatformPorts {
  camera:CameraPort; files:FilesPort; notifications:NotificationsPort;
  share:SharePort; links:LinksPort; session:SessionPort;
}
