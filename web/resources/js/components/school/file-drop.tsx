import { FileText, ImageIcon, Upload, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const ACCEPT = 'application/pdf,image/png,image/jpeg,image/webp,image/gif';

/**
 * Upload box for a letter: drag & drop (computer), tap to choose (phone: photo library,
 * camera or Files), or paste a screenshot with Cmd/Ctrl+V anywhere on the page.
 * Reused later for the family memory "Teach" page and receipts.
 */
export function FileDrop({
    file,
    onChange,
}: {
    file: File | null;
    onChange: (file: File | null) => void;
}) {
    const input = useRef<HTMLInputElement>(null);
    const [dragging, setDragging] = useState(false);
    const [preview, setPreview] = useState<string | null>(null);

    // Paste a screenshot straight from the clipboard.
    useEffect(() => {
        const onPaste = (event: ClipboardEvent) => {
            const pasted = [...(event.clipboardData?.files ?? [])].find((f) =>
                ACCEPT.includes(f.type),
            );

            if (pasted) {
                event.preventDefault();
                onChange(pasted);
            }
        };

        window.addEventListener('paste', onPaste);

        return () => window.removeEventListener('paste', onPaste);
    }, [onChange]);

    // Thumbnail for images (PDFs just show their name).
    useEffect(() => {
        if (!file || !file.type.startsWith('image/')) {
            setPreview(null);

            return;
        }

        const url = URL.createObjectURL(file);
        setPreview(url);

        return () => URL.revokeObjectURL(url);
    }, [file]);

    if (file) {
        return (
            <div className="flex items-center gap-4 rounded-lg border p-3">
                {preview ? (
                    <img
                        src={preview}
                        alt=""
                        className="size-16 rounded object-cover"
                    />
                ) : (
                    <div className="flex size-16 items-center justify-center rounded bg-muted">
                        {file.type === 'application/pdf' ? (
                            <FileText className="size-8 text-muted-foreground" />
                        ) : (
                            <ImageIcon className="size-8 text-muted-foreground" />
                        )}
                    </div>
                )}
                <div className="min-w-0 flex-1">
                    <p className="truncate font-medium">{file.name}</p>
                    <p className="text-sm text-muted-foreground">
                        {(file.size / 1024 / 1024).toFixed(1)} MB
                    </p>
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label="Remove file"
                    onClick={() => {
                        onChange(null);

                        if (input.current) {
                            input.current.value = '';
                        }
                    }}
                >
                    <X className="size-4" />
                </Button>
            </div>
        );
    }

    return (
        <label
            className={cn(
                'flex cursor-pointer flex-col items-center gap-2 rounded-lg border-2 border-dashed p-8 text-center transition-colors',
                dragging
                    ? 'border-primary bg-primary/5'
                    : 'border-muted-foreground/25 hover:border-muted-foreground/50',
            )}
            onDragOver={(event) => {
                event.preventDefault();
                setDragging(true);
            }}
            onDragLeave={() => setDragging(false)}
            onDrop={(event) => {
                event.preventDefault();
                setDragging(false);
                onChange(event.dataTransfer.files[0] ?? null);
            }}
        >
            <Upload className="size-8 text-muted-foreground" />
            <span className="font-medium">
                Drop a PDF or screenshot here, or tap to choose
            </span>
            <span className="text-sm text-muted-foreground">
                You can also paste a screenshot (⌘V)
            </span>
            <input
                ref={input}
                type="file"
                accept={ACCEPT}
                className="sr-only"
                onChange={(event) => onChange(event.target.files?.[0] ?? null)}
            />
        </label>
    );
}
