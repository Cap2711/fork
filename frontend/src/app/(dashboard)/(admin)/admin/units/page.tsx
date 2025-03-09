'use client';

import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { LoadingGrid } from "@/components/ui/loading";
import { useToast } from "@/components/ui/toast";
import { useEffect, useState } from "react";
import { listUnits, UnitResponse } from "@/app/_actions/admin/unit-actions";
import { useRouter } from "next/navigation";

export default function UnitsPage() {
    const router = useRouter();
    const toast = useToast();
    const [units, setUnits] = useState<UnitResponse[]>([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        loadUnits();
    }, []);

    async function loadUnits() {
        try {
            setLoading(true);
            const response = await listUnits();
            setUnits(response.data.units);
        } catch (error) {
            toast.showToast("Failed to load units", "error");
            console.error(error);
        } finally {
            setLoading(false);
        }
    }

    return (
        <div className="container mx-auto py-6">
            <div className="flex justify-between items-center mb-6">
                <div>
                    <h1 className="text-2xl font-bold">Course Units</h1>
                    <p className="text-muted-foreground mt-1">
                        Manage your learning units and their content
                    </p>
                </div>
                <Button 
                    onClick={() => router.push("/admin/units/new")}
                    className="flex items-center gap-2"
                >
                    <PlusIcon className="h-4 w-4" />
                    Create New Unit
                </Button>
            </div>

            {loading ? (
                <LoadingGrid />
            ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    {units.map((unit) => (
                        <Card 
                            key={unit.id} 
                            className="cursor-pointer hover:shadow-lg transition-shadow"
                            onClick={() => router.push(`/admin/units/${unit.id}`)}
                        >
                            <CardHeader>
                                <CardTitle className="flex justify-between items-center">
                                    <span>{unit.name}</span>
                                    <span className={`text-sm px-2 py-1 rounded-full ${
                                        unit.difficulty === 'beginner' ? 'bg-green-100 text-green-800' :
                                        unit.difficulty === 'intermediate' ? 'bg-yellow-100 text-yellow-800' :
                                        'bg-red-100 text-red-800'
                                    }`}>
                                        {unit.difficulty}
                                    </span>
                                </CardTitle>
                                <CardDescription>{unit.description}</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    <div className="grid grid-cols-2 gap-4 text-sm">
                                        <div className="space-y-2">
                                            <p className="text-muted-foreground">Lessons</p>
                                            <p className="font-medium">{unit.lessons.length}</p>
                                        </div>
                                        <div className="space-y-2">
                                            <p className="text-muted-foreground">Completion</p>
                                            <p className="font-medium">{unit.stats.completion_rate}%</p>
                                        </div>
                                        <div className="space-y-2">
                                            <p className="text-muted-foreground">Students</p>
                                            <p className="font-medium">{unit.stats.completed_users}</p>
                                        </div>
                                        <div className="space-y-2">
                                            <p className="text-muted-foreground">Status</p>
                                            <p className={`font-medium ${
                                                unit.is_locked ? 'text-red-600' : 'text-green-600'
                                            }`}>
                                                {unit.is_locked ? 'Locked' : 'Active'}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="w-full bg-gray-100 rounded-full h-2">
                                        <div
                                            className="bg-primary h-2 rounded-full transition-all duration-300"
                                            style={{ width: `${unit.stats.completion_rate}%` }}
                                        />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            )}

            {!loading && units.length === 0 && (
                <div className="text-center py-12">
                    <div className="w-full max-w-sm mx-auto space-y-4">
                        <EmptyIcon className="h-12 w-12 mx-auto text-muted-foreground" />
                        <div className="space-y-2">
                            <h3 className="font-semibold text-lg">No units created</h3>
                            <p className="text-muted-foreground">
                                Get started by creating your first learning unit
                            </p>
                        </div>
                        <Button 
                            onClick={() => router.push("/admin/units/new")}
                            className="mt-4"
                        >
                            Create Your First Unit
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}

function PlusIcon(props: React.SVGProps<SVGSVGElement>) {
    return (
        <svg 
            {...props}
            xmlns="http://www.w3.org/2000/svg" 
            viewBox="0 0 24 24" 
            fill="none" 
            stroke="currentColor" 
            strokeWidth="2" 
            strokeLinecap="round" 
            strokeLinejoin="round"
        >
            <path d="M12 5v14M5 12h14" />
        </svg>
    );
}

function EmptyIcon(props: React.SVGProps<SVGSVGElement>) {
    return (
        <svg 
            {...props}
            xmlns="http://www.w3.org/2000/svg" 
            viewBox="0 0 24 24" 
            fill="none" 
            stroke="currentColor" 
            strokeWidth="2" 
            strokeLinecap="round" 
            strokeLinejoin="round"
        >
            <rect x="3" y="3" width="18" height="18" rx="2" />
            <path d="M12 8v8M8 12h8" />
        </svg>
    );
}