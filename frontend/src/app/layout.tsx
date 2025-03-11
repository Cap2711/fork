import type { Metadata } from "next";
import { Inter } from "next/font/google";
import "./globals.css";
import { RootProvider } from "./providers/root-provider";

const inter = Inter({ subsets: ["latin"] });

export const metadata: Metadata = {
  title: "Language Learning Platform",
  description: "Learn languages through interactive exercises and structured lessons",
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="en">
      <body className={inter.className}>
        <RootProvider>{children}</RootProvider>
      </body>
    </html>
  );
}
