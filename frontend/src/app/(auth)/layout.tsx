import Image from 'next/image';

export default function AuthLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <div className="min-h-screen bg-[#235390] flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
      <div className="max-w-md w-full">
        {/* Duo Mascot */}
        <div className="text-center mb-8">
          <div className="relative w-32 h-32 mx-auto">
            <Image
              src="/duo-waving.svg"
              alt="Duo the owl"
              fill
              className="object-contain"
              priority
            />
          </div>
        </div>

        {/* Auth Card */}
        <div className="bg-white rounded-2xl shadow-xl p-8 space-y-6">
          <div className="text-center">
            <h2 className="text-3xl font-black text-[#3c3c3c] tracking-tight">
              English Learning
            </h2>
            <p className="mt-2 text-lg text-gray-600">
              Learn English the fun way!
            </p>
          </div>
          {children}
        </div>

        {/* Language Learning Progress Visual */}
        <div className="mt-8 flex justify-center space-x-4">
          {[...Array(5)].map((_, i) => (
            <div
              key={i}
              className="w-3 h-3 rounded-full bg-white/30 first:bg-white"
            />
          ))}
        </div>
      </div>
    </div>
  );
}