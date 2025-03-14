'use client';

import { Button } from '@/components/ui/button';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { cn } from '@/lib/utils';

const navigation = [
  {
    title: 'Content Management',
    items: [
      { name: 'Learning Paths', href: '/admin/learning-paths' },
      { name: 'Units', href: '/admin/units' },
      { name: 'Lessons', href: '/admin/lessons' },
      { name: 'Quizzes', href: '/admin/quizzes' },
      { name: 'Exercises', href: '/admin/exercises' },
      { name: 'Vocabulary', href: '/admin/vocabulary' },
    ],
  },
  {
    title: 'User Management',
    items: [
      { name: 'Users', href: '/admin/users' },
      { name: 'Roles', href: '/admin/roles' },
      { name: 'Permissions', href: '/admin/permissions' },
    ],
  },
  {
    title: 'Analytics',
    items: [
      { name: 'Progress Tracking', href: '/admin/progress' },
      { name: 'XP System', href: '/admin/xp' },
      { name: 'Reports', href: '/admin/reports' },
    ],
  },
];

interface AdminSidebarProps {
  isOpen: boolean;
  onClose: () => void;
}

export default function AdminSidebar({ isOpen, onClose }: AdminSidebarProps) {
  const pathname = usePathname();

  return (
    <>
      {/* Backdrop */}
      <div 
        className={cn(
          "fixed inset-0 bg-black/50 z-40 lg:hidden transition-opacity duration-200",
          isOpen ? "opacity-100" : "opacity-0 pointer-events-none"
        )}
        onClick={onClose}
        aria-hidden="true"
      />

      {/* Sidebar */}
      <aside
        className={cn(
          "fixed top-0 left-0 z-50 h-full w-64 bg-white shadow-lg transform transition-transform duration-200 ease-in-out lg:translate-x-0 lg:z-30 lg:static",
          isOpen ? "translate-x-0" : "-translate-x-full"
        )}
      >
        {/* Close button - mobile only */}
        <button
          onClick={onClose}
          className="lg:hidden absolute right-4 top-4 p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-md"
          aria-label="Close sidebar"
        >
          <svg
            className="h-5 w-5"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M6 18L18 6M6 6l12 12"
            />
          </svg>
        </button>

        {/* Logo */}
        <div className="p-4 border-b">
          <Link href="/admin" className="flex items-center space-x-2">
            <span className="text-xl font-bold">Admin Dashboard</span>
          </Link>
        </div>

        {/* Navigation */}
        <nav className="p-4 space-y-6">
          {navigation.map((section) => (
            <div key={section.title} className="space-y-3">
              <h3 className="text-sm font-semibold text-gray-500 uppercase tracking-wider px-3">
                {section.title}
              </h3>
              <div className="space-y-1">
                {section.items.map((item) => (
                  <Link
                    key={item.href}
                    href={item.href}
                    className={cn(
                      "flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors",
                      pathname === item.href
                        ? "bg-primary/10 text-primary"
                        : "text-gray-700 hover:bg-gray-50 hover:text-gray-900"
                    )}
                    onClick={() => {
                      if (window.innerWidth < 1024) {
                        onClose();
                      }
                    }}
                  >
                    {item.name}
                  </Link>
                ))}
              </div>
            </div>
          ))}
        </nav>
      </aside>
    </>
  );
}