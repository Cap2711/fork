import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { UserRole } from "@/types/user";

export async function middleware(request: NextRequest) {
  const token = request.cookies.get("token")?.value;
  const userDataCookie = request.cookies.get("user_data")?.value;

  // For login and register pages, redirect authenticated users
  if (
    request.nextUrl.pathname === "/login" ||
    request.nextUrl.pathname === "/register"
  ) {
    if (token && userDataCookie) {
      try {
        const userData = JSON.parse(decodeURIComponent(userDataCookie));
        const role = userData.role;

        // Redirect to appropriate dashboard based on role
        return NextResponse.redirect(
          new URL(role === UserRole.ADMIN ? "/admin" : "/learn", request.url)
        );
      } catch (error) {
        console.error("Error parsing user data:", error);
      }
    }
  }

  // Check if trying to access dashboard routes
  if (
    request.nextUrl.pathname.startsWith("/admin") ||
    request.nextUrl.pathname.startsWith("/learn")
  ) {
    console.log("TOKEN FROM COOKIE: ", token);
    console.log("USER DATA FROM COOKIE: ", userDataCookie);

    // If no token or user data, redirect to login
    if (!token || !userDataCookie) {
      return NextResponse.redirect(new URL("/login", request.url));
    }

    let role: UserRole | null = null;
    try {
      const userData = JSON.parse(decodeURIComponent(userDataCookie));
      role = userData.role;
    } catch (error) {
      console.error("Error parsing user data:", error);
      return NextResponse.redirect(new URL("/login", request.url));
    }

    // If no role is found, redirect to login
    if (!role) {
      return NextResponse.redirect(new URL("/login", request.url));
    }

    // Check if user is trying to access admin routes
    if (request.nextUrl.pathname.startsWith("/admin")) {
      if (role !== UserRole.ADMIN) {
        // Redirect non-admin users to learn dashboard
        return NextResponse.redirect(new URL("/learn", request.url));
      }
    }

    // Check if user is trying to access learn routes
    if (request.nextUrl.pathname.startsWith("/learn")) {
      if (role === UserRole.ADMIN) {
        // Redirect admin users to admin dashboard
        return NextResponse.redirect(new URL("/admin", request.url));
      }
    }
  }

  // Allow all other routes to pass through
  return NextResponse.next();
}

// Configure the middleware to run on specific paths
export const config = {
  matcher: [
    // Match all dashboard routes
    "/admin/:path*",
    "/learn/:path*",
    "/login",
    "/register",
  ],
};
