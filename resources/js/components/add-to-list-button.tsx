import { useEffect, useRef, useState } from 'react';
import { useUserLists, useAddGameToList, VnList } from '@/hooks/api';

interface AddToListButtonProps {
  gameId: number;
  gameName: string;
  className?: string;
}

export default function AddToListButton({
  gameId,
  gameName,
  className = '',
}: AddToListButtonProps) {
  const { data: lists = [] } = useUserLists();
  const addToListMutation = useAddGameToList();

  const [isOpen, setIsOpen] = useState(false);
  const [selectedListId, setSelectedListId] = useState<number | null>(null);
  const dialogRef = useRef<HTMLDialogElement>(null);
  const closeBtnRef = useRef<HTMLButtonElement>(null);
  const openerRef = useRef<HTMLElement | null>(null);

  const handleAddToList = async () => {
    if (!selectedListId) return;

    addToListMutation.mutate(
      { gameId, listId: selectedListId },
      {
        onSuccess: (data) => {
          if (data.success) {
            const event = new CustomEvent('show-toast', {
              detail: {
                message: `Added "${gameName}" to list successfully!`,
                type: 'success',
              },
            });
            document.dispatchEvent(event);
            setIsOpen(false);
            setSelectedListId(null);
          } else {
            throw new Error(data.message || 'Failed to add game to list');
          }
        },
        onError: (error) => {
          console.error('Error adding game to list:', error);
          const event = new CustomEvent('show-toast', {
            detail: {
              message: error instanceof Error ? error.message : 'Failed to add game to list',
              type: 'error',
            },
          });
          document.dispatchEvent(event);
        },
      }
    );
  };

  const getListTypeColor = (type: string) => {
    switch (type) {
      case 'reading':
        return 'blue';
      case 'completed':
        return 'green';
      case 'plan_to_read':
        return 'yellow';
      case 'on_hold':
        return 'orange';
      case 'dropped':
        return 'red';
      default:
        return 'gray';
    }
  };

  // Manage dialog open/close
  useEffect(() => {
    const dlg = dialogRef.current;
    if (!dlg) return;
    if (isOpen) {
      openerRef.current = (document.activeElement as HTMLElement) || null;
      if (!dlg.open) dlg.showModal();
      requestAnimationFrame(() => closeBtnRef.current?.focus());
    } else if (dlg.open) {
      dlg.close();
    }
  }, [isOpen]);

  useEffect(() => {
    const dlg = dialogRef.current;
    if (!dlg) return;
    const handleClose = () => {
      setIsOpen(false);
      setSelectedListId(null);
      openerRef.current?.focus?.();
      openerRef.current = null;
    };
    dlg.addEventListener('close', handleClose);
    return () => dlg.removeEventListener('close', handleClose);
  }, []);

  if (lists.length === 0) {
    return null;
  }

  return (
    <div className="relative">
      <button
        onClick={() => setIsOpen(!isOpen)}
        disabled={false}
        className={`inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 font-medium text-white transition-colors hover:bg-blue-700 disabled:opacity-50 ${className}`}
      >
        <svg
          className="mr-2 h-5 w-5"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M12 6v6m0 0v6m0-6h6m-6 0H6"
          />
        </svg>
        Add to List
      </button>

      <dialog
        ref={dialogRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby="add-to-list-title"
        className="m-auto w-80 rounded-lg border border-gray-200 bg-white p-0 shadow-lg backdrop:bg-black/20 dark:border-gray-700 dark:bg-gray-800"
        onClick={(e) => {
          if (e.target === e.currentTarget) setIsOpen(false);
        }}
      >
        <div className="p-4">
          <h3 id="add-to-list-title" className="mb-4 text-lg font-medium text-gray-900 dark:text-white">
            Add "{gameName}" to a list
          </h3>

          <div className="max-h-60 space-y-2 overflow-y-auto">
            {lists.map((list: VnList) => {
              const color = getListTypeColor(list.type);
              return (
                <label
                  key={list.id}
                  className="flex cursor-pointer items-center rounded-lg border border-gray-200 p-3 hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-700"
                >
                  <input
                    type="radio"
                    name="list"
                    value={list.id}
                    checked={selectedListId === list.id}
                    onChange={(e) => setSelectedListId(Number(e.target.value))}
                    className="mr-3 text-blue-600 focus:ring-blue-500"
                  />
                  <div className="flex-1">
                    <div className="flex items-center justify-between">
                      <span className="font-medium text-gray-900 dark:text-white">
                        {list.name}
                      </span>
                      {!list.is_default && (
                        <span
                          className={`rounded-full px-2 py-1 text-xs font-semibold bg-${color}-100 text-${color}-800 dark:bg-${color}-900/20 dark:text-${color}-400`}
                        >
                          {list.type
                            .replace(/_/g, ' ')
                            .replace(/\b\w/g, (l) => l.toUpperCase())}
                        </span>
                      )}
                    </div>
                    {list.is_default && (
                      <span className="text-xs text-gray-500 dark:text-gray-400">
                        Default list
                      </span>
                    )}
                  </div>
                </label>
              );
            })}
          </div>

          <div className="mt-4 flex justify-end space-x-3 border-t border-gray-200 pt-4 dark:border-gray-600">
            <button
              ref={closeBtnRef}
              onClick={() => {
                setIsOpen(false);
                setSelectedListId(null);
              }}
              className="rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
              aria-label="Close dialog"
            >
              Cancel
            </button>
            <button
              onClick={handleAddToList}
              disabled={!selectedListId || addToListMutation.isPending}
              className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
            >
              {addToListMutation.isPending ? 'Adding...' : 'Add to List'}
            </button>
          </div>
        </div>
      </dialog>
    </div>
  );
}
