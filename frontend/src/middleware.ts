import { NextResponse } from 'next/server'
import type { NextRequest } from 'next/server'
import { UserRole } from '@/types/user'

export async function middleware(request: NextRequest) {
  // Get token and user data from cookies
  const token = request.cookies.get('token')?.value
  const userDataCookie = request.cookies.get('user_data')?.value

  // Check if user is trying to access auth routes (login/register)
  if (request.nextUrl.pathname.startsWith('/login') || 
      request.nextUrl.pathname.startsWith('/register')) {
    // If user is already authenticated, redirect to appropriate dashboard
    if (token && userDataCookie) {
      try {
        const userData = JSON.parse(decodeURIComponent(userDataCookie))
        if (userData.role === UserRole.ADMIN) {
          return NextResponse.redirect(new URL('/admin', request.url))
        } else {
          return NextResponse.redirect(new URL('/learn', request.url))
        }
      } catch (error) {
        console.error('Error parsing user data:', error)
      }
    }
  }

  // If no token or user data is present, redirect to login
  if (!token || !userDataCookie) {
    return NextResponse.redirect(new URL('/login', request.url))
  }

  let role: UserRole | null = null;
  try {
    const userData = JSON.parse(decodeURIComponent(userDataCookie))
    role = userData.role
  } catch (error) {
    console.error('Error parsing user data:', error)
    return NextResponse.redirect(new URL('/login', request.url))
  }

  // If no role is found, redirect to login
  if (!role) {
    return NextResponse.redirect(new URL('/login', request.url))
  }
  
  // Check if user is trying to access admin routes
  if (request.nextUrl.pathname.startsWith('/admin')) {
    if (role !== UserRole.ADMIN) {
      // Redirect non-admin users to learn dashboard
      return NextResponse.redirect(new URL('/learn', request.url))
    }
  }

  // Check if user is trying to access learn routes
  if (request.nextUrl.pathname.startsWith('/learn')) {
    if (role === UserRole.ADMIN) {
      // Redirect admin users to admin dashboard
      return NextResponse.redirect(new URL('/admin', request.url))
    }
  }

  return NextResponse.next()
}

// Configure the middleware to run on specific paths
export const config = {
  matcher: [
    // Match all admin routes
    '/login/:path*',
    '/register/:path*',
    '/admin/:path*',
    // Match all learn routes
    '/learn/:path*',
  ],
}