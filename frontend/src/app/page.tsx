import Link from 'next/link';

export default function Home() {
  return (
    <div className="flex flex-col items-center justify-center min-h-screen p-8">
      <main className="w-full max-w-md space-y-8">
        <div className="text-center">
          <h1 className="text-4xl font-bold mb-4">Start Learning Today</h1>
          <p className="text-gray-600 mb-8">Join millions of students who are already learning with us!</p>
        </div>

        <div className="space-y-4">
          <Link 
            href="/register"
            className="w-full duo-button bg-[var(--duo-green)] border-[var(--duo-green-hover)] flex items-center justify-center"
          >
            Get Started
          </Link>

          <Link
            href="/login"
            className="w-full duo-button bg-white text-[var(--duo-green)] border-[var(--duo-green)] hover:bg-gray-50 flex items-center justify-center"
          >
            I Already Have An Account
          </Link>

          <div className="text-center mt-8">
            <p className="text-sm text-gray-600">
              By signing up, you agree to our{' '}
              <Link href="/terms" className="text-[var(--duo-blue)] hover:text-[var(--duo-blue-hover)]">
                Terms of Service
              </Link>
              {' '}and{' '}
              <Link href="/privacy" className="text-[var(--duo-blue)] hover:text-[var(--duo-blue-hover)]">
                Privacy Policy
              </Link>
            </p>
          </div>
        </div>
      </main>
    </div>
  );
}
