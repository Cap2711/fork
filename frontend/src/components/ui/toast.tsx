'use client';

import { createContext, useContext, useEffect, useState } from 'react';
import { createPortal } from 'react-dom';

interface Toast {
    id: number;
    message: string;
    type: 'success' | 'error' | 'info';
}

interface ToastContextType {
    showToast: (message: string, type: Toast['type']) => void;
}

const ToastContext = createContext<ToastContextType | null>(null);

export function useToast() {
    const context = useContext(ToastContext);
    if (!context) {
        throw new Error('useToast must be used within a ToastProvider');
    }
    return context;
}

export function ToastProvider({ children }: { children: React.ReactNode }) {
    const [toasts, setToasts] = useState<Toast[]>([]);
    const [mounted, setMounted] = useState(false);

    useEffect(() => {
        setMounted(true);
        return () => setMounted(false);
    }, []);

    const showToast = (message: string, type: Toast['type'] = 'info') => {
        const id = Date.now();
        setToasts(prev => [...prev, { id, message, type }]);
        setTimeout(() => {
            setToasts(prev => prev.filter(toast => toast.id !== id));
        }, 3000);
    };

    if (!mounted) return null;

    return (
        <ToastContext.Provider value={{ showToast }}>
            {children}
            {createPortal(
                <div className="fixed top-4 right-4 z-50 flex flex-col gap-2">
                    {toasts.map(toast => (
                        <div
                            key={toast.id}
                            className={`rounded-lg px-4 py-3 text-sm font-medium text-white shadow-lg transform transition-all duration-300 ${
                                toast.type === 'error' ? 'bg-red-500' :
                                toast.type === 'success' ? 'bg-green-500' :
                                'bg-blue-500'
                            }`}
                        >
                            {toast.message}
                        </div>
                    ))}
                </div>,
                document.body
            )}
        </ToastContext.Provider>
    );
}

export function useShowToast() {
    const { showToast } = useToast();
    
    return {
        error: (message: string) => showToast(message, 'error'),
        success: (message: string) => showToast(message, 'success'),
        info: (message: string) => showToast(message, 'info')
    };
}

// Export a toast object that uses the hook internally
function createToastApi() {
    let currentShowToast: ToastContextType['showToast'] | null = null;

    function setToastFunction(showToast: ToastContextType['showToast']) {
        currentShowToast = showToast;
    }

    return {
        error: (message: string) => currentShowToast?.(message, 'error'),
        success: (message: string) => currentShowToast?.(message, 'success'),
        info: (message: string) => currentShowToast?.(message, 'info'),
        _setToastFunction: setToastFunction
    };
}

export const toast = createToastApi();

// Update the toast functions when the context changes
export function ToastUpdater() {
    const { showToast } = useToast();
    
    useEffect(() => {
        toast._setToastFunction(showToast);
        return () => toast._setToastFunction(() => {});
    }, [showToast]);
    
    return null;
}