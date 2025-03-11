export default function LoginLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <>
      <div className="text-center">
        <h2 className="text-3xl font-bold">Welcome Back</h2>
        <p className="mt-2 text-gray-600">Sign in to your account to continue learning</p>
      </div>
      {children}
    </>
  );
}