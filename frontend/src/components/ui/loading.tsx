export function LoadingSpinner() {
    return (
        <div className="flex items-center justify-center">
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
        </div>
    );
}

export function LoadingPage() {
    return (
        <div className="flex items-center justify-center min-h-[400px]">
            <div className="space-y-4 text-center">
                <LoadingSpinner />
                <p className="text-muted-foreground">Loading...</p>
            </div>
        </div>
    );
}

export function LoadingCard() {
    return (
        <div className="rounded-lg border p-6 space-y-4">
            <div className="h-6 bg-muted rounded animate-pulse w-2/3"></div>
            <div className="space-y-2">
                <div className="h-4 bg-muted rounded animate-pulse w-full"></div>
                <div className="h-4 bg-muted rounded animate-pulse w-4/5"></div>
            </div>
        </div>
    );
}

export function LoadingGrid({ count = 6 }: { count?: number }) {
    return (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {Array.from({ length: count }).map((_, i) => (
                <LoadingCard key={i} />
            ))}
        </div>
    );
}