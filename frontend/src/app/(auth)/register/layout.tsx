export default function RegisterLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <div className="flex min-h-screen flex-col items-center justify-center p-4">
      <div className="w-full max-w-md space-y-8 bg-white p-8 rounded-2xl shadow-lg">
        <div className="text-center">
          <h2 className="text-3xl font-bold">Create Your Account</h2>
          <p className="mt-2 text-gray-600">Join millions of learners worldwide</p>
        </div>
        {children}
      </div>
    </div>
  );
}