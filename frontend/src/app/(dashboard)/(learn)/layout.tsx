"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import type { ReactNode } from "react";
import { Button } from "@/components/ui/button";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
  SheetFooter,
  SheetClose
} from "@/components/ui/sheet";
import { Menu } from "lucide-react";
import TopNavBar from "./TopNavBar";

interface LayoutProps {
  children: ReactNode;
}

export default function LearnDashboardLayout({ children }: LayoutProps) {
  const router = useRouter();
  const [isMobile, setIsMobile] = useState(false);

  useEffect(() => {
    
    setIsMobile(window.innerWidth < 768);
    const handleResize = () => setIsMobile(window.innerWidth < 768);
    window.addEventListener("resize", handleResize);
    return () => window.removeEventListener("resize", handleResize);
  }, [router]);

  const NavContent = () => (
    <nav className="space-y-2">
      <Button variant="ghost" className="justify-start w-full" asChild>
        <Link href="/learn">Learning Dashboard</Link>
      </Button>
      <Button variant="ghost" className="justify-start w-full" asChild>
        <Link href="/admin">Admin Panel</Link>
      </Button>
      <Button variant="ghost" className="justify-start w-full" asChild>
        <Link href="/profile">Profile</Link>
      </Button>
    </nav>
  );

  return (
    <div className="min-h-screen bg-background">
      {/* Mobile Navigation */}
      <div className="flex flex-col">
        {/* Desktop Navigation */}
        <div className="bg-background">
          <TopNavBar />
        </div>

        {/* {!isMobile && (
          <div className="w-64 min-h-screen p-4 border-r">
            <Sheet>
              <SheetTrigger asChild>
                <Button variant="outline">Click me</Button>
              </SheetTrigger>
              <SheetContent side="left" className="w-64">
                <SheetHeader>
                  <SheetTitle></SheetTitle>
                </SheetHeader>
                 
                  <NavContent />
              </SheetContent>
            </Sheet>
           
          </div>
        )} */}

        {/* Main Content */}
        <main className="flex">
          <div className="p-0">{children}</div>
        </main>
      </div>
    </div>
  );
}
