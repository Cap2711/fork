'use client';

import { AuthProvider } from './auth-provider';
import { ToastProvider, ToastUpdater } from '@/components/ui/toast';

export function RootProvider({ children }: { children: React.ReactNode }) {
    return (
        <AuthProvider>
            <ToastProvider>
                {children}
                <ToastUpdater />
            </ToastProvider>
        </AuthProvider>
    );
}