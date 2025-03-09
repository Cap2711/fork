export default function RegisterLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <>
      <div className="text-center">
        <h2 className="text-3xl font-bold">Create Your Account</h2>
        <p className="mt-2 text-gray-600">Join millions of learners worldwide</p>
      </div>
      {children}
    </>
  );
}