'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetTrigger } from '@/components/ui/sheet';
import { Menu } from 'lucide-react';

interface LayoutProps {
  children: ReactNode;
}

export default function DashboardLayout({ children }: LayoutProps) {
  const router = useRouter();
  const [isMobile, setIsMobile] = useState(false);

  useEffect(() => {
    const token = localStorage.getItem('token');
    if (!token) {
      router.push('/login');
    }
    setIsMobile(window.innerWidth < 768);
    const handleResize = () => setIsMobile(window.innerWidth < 768);
    window.addEventListener('resize', handleResize);
    return () => window.removeEventListener('resize', handleResize);
  }, [router]);

  const NavContent = () => (
    <nav className="space-y-2">
      <Button variant="ghost" className="w-full justify-start" asChild>
        <Link href="/learn">
          Learning Dashboard
        </Link>
      </Button>
      <Button variant="ghost" className="w-full justify-start" asChild>
        <Link href="/admin">
          Admin Panel
        </Link>
      </Button>
      <Button variant="ghost" className="w-full justify-start" asChild>
        <Link href="/profile">
          Profile
        </Link>
      </Button>
    </nav>
  );

  return (
    <div className="min-h-screen bg-background">
      {/* Mobile Navigation */}
      {isMobile && (
        <header className="flex h-16 items-center border-b px-4">
          <Sheet>
            <SheetTrigger asChild>
              <Button variant="ghost" size="icon" className="lg:hidden">
                <Menu className="h-5 w-5" />
                <span className="sr-only">Toggle navigation menu</span>
              </Button>
            </SheetTrigger>
            <SheetContent side="left" className="w-64">
              <div className="mt-6">
                <NavContent />
              </div>
            </SheetContent>
          </Sheet>
          <div className="flex-1" />
          <Button
            variant="ghost"
            onClick={() => {
              localStorage.removeItem('token');
              router.push('/login');
            }}
          >
            Sign Out
          </Button>
        </header>
      )}

      <div className="flex">
        {/* Desktop Navigation */}
        {!isMobile && (
          <div className="w-64 border-r min-h-screen p-4">
            <NavContent />
          </div>
        )}

        {/* Main Content */}
        <main className="flex-1">
          {!isMobile && (
            <header className="border-b px-8 py-4">
              <div className="flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Dashboard</h1>
                <Button
                  variant="ghost"
                  onClick={() => {
                    localStorage.removeItem('token');
                    router.push('/login');
                  }}
                >
                  Sign Out
                </Button>
              </div>
            </header>
          )}
          <div className="p-8">
            {children}
          </div>
        </main>
      </div>
    </div>
  );
}