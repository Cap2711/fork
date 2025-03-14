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
      {isOpen && (
        <div
          className="fixed inset-0 bg-black/50 z-40 lg:hidden"
          onClick={onClose}
        />
      )}

      {/* Sidebar */}
      <aside
        className={cn(
          "fixed top-0 left-0 z-50 h-full w-64 bg-white shadow-lg transform transition-transform duration-200 ease-in-out lg:translate-x-0 lg:static lg:z-0",
          isOpen ? "translate-x-0" : "-translate-x-full"
        )}
      >
        {/* Close button - mobile only */}
        <button
          onClick={onClose}
          className="lg:hidden absolute top-4 right-4 p-2 text-gray-500 hover:text-gray-700"
        >
          <svg
            className="h-6 w-6"
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
          <Link href="/admin" className="text-xl font-bold">
            Admin Dashboard
          </Link>
        </div>

        {/* Navigation */}
        <nav className="p-4 space-y-8">
          {navigation.map((section) => (
            <div key={section.title}>
              <h3 className="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">
                {section.title}
              </h3>
              <div className="space-y-1">
                {section.items.map((item) => (
                  <Link
                    key={item.href}
                    href={item.href}
                    className={cn(
                      "block px-3 py-2 rounded-md text-sm font-medium transition-colors",
                      pathname === item.href
                        ? "bg-gray-100 text-gray-900"
                        : "text-gray-600 hover:bg-gray-50 hover:text-gray-900"
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