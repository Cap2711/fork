'use client';

import { ToastProvider, ToastUpdater } from "@/components/ui/toast";
import { AuthProvider } from "./providers/auth-provider";

export function Providers({ children }: { children: React.ReactNode }) {
    return (
        <AuthProvider>
            <ToastProvider>
                {children}
                <ToastUpdater />
            </ToastProvider>
        </AuthProvider>
    );
}