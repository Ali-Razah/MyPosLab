export type MplWhatsAppConfig = {
    mode: 'link' | 'cloud_api';
    posPrompt: boolean;
    autoSend: boolean;
    apiBase: string;
};

export function config(): MplWhatsAppConfig {
    return (window as any).MplWhatsAppConfig as MplWhatsAppConfig;
}
