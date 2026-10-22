import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import documentEditor from './editor';

Alpine.data('documentEditor', documentEditor);

Livewire.start();
