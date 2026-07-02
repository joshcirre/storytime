import { Form, Head } from '@inertiajs/react';
import { ImageUp, PencilLine } from 'lucide-react';
import { useState } from 'react';
import CharacterController from '@/actions/App/Http/Controllers/CharacterController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { create, index } from '@/routes/characters';

export default function CharactersCreate({
    voices,
}: {
    voices: Record<string, string>;
}) {
    const [mode, setMode] = useState<'drawing' | 'prompt'>('drawing');
    const [preview, setPreview] = useState<string | null>(null);

    return (
        <>
            <Head title="New character" />

            <div className="flex flex-col gap-6 p-4">
                <Heading
                    title="New character"
                    description="Upload a drawing or describe a character, and Runway will bring it to life"
                />

                <Form
                    {...CharacterController.store.form()}
                    className="max-w-xl space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    maxLength={50}
                                    placeholder="Sparkles the Dragon"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="personality">Personality</Label>
                                <Input
                                    id="personality"
                                    name="personality"
                                    required
                                    maxLength={500}
                                    placeholder="A brave dragon who loves tacos and terrible puns"
                                />
                                <p className="text-sm text-muted-foreground">
                                    One sentence about who they are — this
                                    shapes how they talk.
                                </p>
                                <InputError message={errors.personality} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="voice">Voice</Label>
                                <Select name="voice" defaultValue="ruby">
                                    <SelectTrigger id="voice">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {Object.entries(voices).map(
                                            ([id, label]) => (
                                                <SelectItem key={id} value={id}>
                                                    {label}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.voice} />
                            </div>

                            <div className="grid gap-2">
                                <Label>Start from</Label>
                                <ToggleGroup
                                    type="single"
                                    variant="outline"
                                    value={mode}
                                    onValueChange={(value) => {
                                        if (value) {
                                            setMode(
                                                value as 'drawing' | 'prompt',
                                            );
                                        }
                                    }}
                                    className="justify-start"
                                >
                                    <ToggleGroupItem value="drawing">
                                        <ImageUp />A drawing
                                    </ToggleGroupItem>
                                    <ToggleGroupItem value="prompt">
                                        <PencilLine />A description
                                    </ToggleGroupItem>
                                </ToggleGroup>
                            </div>

                            {mode === 'drawing' ? (
                                <div className="grid gap-2">
                                    <Label htmlFor="drawing">The drawing</Label>
                                    <Input
                                        id="drawing"
                                        name="drawing"
                                        type="file"
                                        accept="image/png,image/jpeg,image/webp"
                                        onChange={(event) => {
                                            const file =
                                                event.target.files?.[0];
                                            setPreview(
                                                file
                                                    ? URL.createObjectURL(file)
                                                    : null,
                                            );
                                        }}
                                    />
                                    {preview && (
                                        <img
                                            src={preview}
                                            alt="Drawing preview"
                                            className="max-h-64 w-fit rounded-lg border object-contain"
                                        />
                                    )}
                                    <InputError message={errors.drawing} />
                                </div>
                            ) : (
                                <div className="grid gap-2">
                                    <Label htmlFor="prompt">
                                        Describe the character
                                    </Label>
                                    <Input
                                        id="prompt"
                                        name="prompt"
                                        maxLength={500}
                                        placeholder="A purple dragon with tiny wings and a big goofy grin"
                                    />
                                    <InputError message={errors.prompt} />
                                </div>
                            )}

                            <Button disabled={processing}>
                                {processing
                                    ? 'Creating...'
                                    : 'Bring them to life'}
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

CharactersCreate.layout = {
    breadcrumbs: [
        {
            title: 'Characters',
            href: index(),
        },
        {
            title: 'New character',
            href: create(),
        },
    ],
};
