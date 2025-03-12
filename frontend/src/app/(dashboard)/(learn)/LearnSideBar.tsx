import Link from 'next/link'
import React from 'react'
import { Button } from "@/components/ui/button";

export default function LearnSideBar() {
  return (
    <div> 
        <div className="p-1">
            Cree Quest
        </div>
        <hr className='p-1' />
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
        </nav></div>
  )
}
