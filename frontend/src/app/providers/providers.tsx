'use client';

import { AuthProvider } from './auth-provider';
import { ToastProvider } from './toast-provider';

export function Providers({ children }: { children: React.ReactNode }) {
    return (
        <AuthProvider>
            <ToastProvider>
                {children}
            </ToastProvider>
        </AuthProvider>
    );
}