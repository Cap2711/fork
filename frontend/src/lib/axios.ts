import axios from "axios";

import { cookies } from "next/headers";

const axiosInstance = axios.create({
  baseURL: process.env.NEXT_PUBLIC_API_URL,
  withCredentials: true,
}); 

// Request interceptor to add auth token
axiosInstance.interceptors.request.use(
  async (config: any) => {
    const cookieStore = await cookies();
    const token = cookieStore.get("token")?.value;

    if (token && config.headers) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Response interceptor for error handling
axiosInstance.interceptors.response.use(
  (response) => response,
  async (error) => {
    // Check if error is from axios
    if (error?.isAxiosError) {
      // Handle token expiration
      if (error.response?.status === 401) {
        const cookieStore = await cookies();
        cookieStore.set("token", "", { maxAge: 0 }); // This effectively deletes the cookie
      }

      throw new Error(error.response?.data?.message || error.message);
    }
    throw new Error("An unexpected error occurred");
  }
);

export interface ApiResponse<T> {
  data: T;
  message: string;
  status: number;
}

export default axiosInstance;
