"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import type { ReactNode } from "react";
import { Button } from "@/components/ui/button";
 
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

  

  return (
    <div className="min-h-screen bg-background">
       <div className="flex flex-col">
        {/* Navigation */}
        <div className="bg-background">
          <TopNavBar />
        </div>

        

        {/* Main Content */}
        <main className="flex">
          <div className="p-0">{children}</div>
        </main>
      </div>
    </div>
  );
}
