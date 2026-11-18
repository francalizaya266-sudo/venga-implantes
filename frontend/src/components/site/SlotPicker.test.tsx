import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { SlotPicker } from '@/components/site/SlotPicker';
import { day, slot } from '@/test/fixtures';

const days = [day('2026-11-04', 'Miércoles 4 de noviembre'), day('2026-11-05', 'Jueves 5 de noviembre')];
const slots = [
  slot('09:00', '9:00 a. m.'),
  slot('09:30', '9:30 a. m.', false),
  slot('15:00', '3:00 p. m.'),
  slot('16:30', '4:30 p. m.', false),
];

function times(): string[] {
  return screen.getAllByRole('button', { name: /\d:\d\d [ap]\. m\./ }).map((button) => button.textContent);
}

describe('SlotPicker', () => {
  it('offers first the times that leave no gaps in the agenda, by morning and afternoon', async () => {
    const user = userEvent.setup();
    render(<SlotPicker days={days} date="2026-11-04" slots={slots} onDate={vi.fn()} onTime={vi.fn()} />);

    expect(screen.getByRole('heading', { name: 'Miércoles 4 de noviembre' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Miércoles 4 de noviembre' })).toHaveAttribute('aria-pressed', 'true');
    expect(screen.getByRole('button', { name: 'Jueves 5 de noviembre' })).toHaveAttribute('aria-pressed', 'false');
    expect(screen.getByText('Por la mañana')).toBeInTheDocument();
    expect(screen.getByText('Por la tarde')).toBeInTheDocument();
    expect(times()).toEqual(['9:00 a. m.', '3:00 p. m.']);

    await user.click(screen.getByRole('button', { name: 'Ver más horarios' }));

    expect(times()).toEqual(['9:00 a. m.', '9:30 a. m.', '3:00 p. m.', '4:30 p. m.']);
    expect(screen.queryByRole('button', { name: 'Ver más horarios' })).not.toBeInTheDocument();
  });

  it('does not offer more times when all of them are already on screen', () => {
    render(
      <SlotPicker days={days} date="2026-11-04" slots={[slots[0]!, slots[2]!]} onDate={vi.fn()} onTime={vi.fn()} />,
    );

    expect(screen.queryByRole('button', { name: 'Ver más horarios' })).not.toBeInTheDocument();
  });

  it('tells which day and which time the patient touched', async () => {
    const user = userEvent.setup();
    const onDate = vi.fn();
    const onTime = vi.fn();
    render(<SlotPicker days={days} date="2026-11-04" slots={slots} onDate={onDate} onTime={onTime} />);

    await user.click(screen.getByRole('button', { name: 'Jueves 5 de noviembre' }));
    await user.click(screen.getByRole('button', { name: '3:00 p. m.' }));

    expect(onDate).toHaveBeenCalledWith('2026-11-05');
    expect(onTime).toHaveBeenCalledWith(slots[2]);
  });

  it('marks the chosen times and, with all the options taken, lets only those be touched', () => {
    render(
      <SlotPicker
        days={days}
        date="2026-11-04"
        slots={slots}
        selected={['2026-11-04 09:00', '2026-11-05 15:00']}
        onDate={vi.fn()}
        onTime={vi.fn()}
        note="Ya elegiste tus 2 horarios. Quita uno para elegir otro."
        full
      />,
    );

    expect(screen.getByRole('status')).toHaveTextContent('Ya elegiste tus 2 horarios. Quita uno para elegir otro.');
    expect(screen.getByRole('button', { name: '9:00 a. m.' })).toHaveAttribute('aria-pressed', 'true');
    expect(screen.getByRole('button', { name: '9:00 a. m.' })).toBeEnabled();
    // El de las 3:00 p. m. elegido es de otro día: el de hoy no está marcado.
    expect(screen.getByRole('button', { name: '3:00 p. m.' })).toHaveAttribute('aria-pressed', 'false');
    expect(screen.getByRole('button', { name: '3:00 p. m.' })).toBeDisabled();
  });

  it('starts again with the suggested times when the day changes', async () => {
    const user = userEvent.setup();
    const props = { days, slots, onDate: vi.fn(), onTime: vi.fn() };
    const { rerender } = render(<SlotPicker {...props} date="2026-11-04" />);

    await user.click(screen.getByRole('button', { name: 'Ver más horarios' }));
    rerender(<SlotPicker {...props} date="2026-11-05" />);

    expect(times()).toEqual(['9:00 a. m.', '3:00 p. m.']);
  });

  it.each([
    [false, 'No encontramos horarios libres en las próximas semanas. Escríbenos'],
    [true, 'No encontramos horarios libres en las próximas semanas con este doctor. Escríbenos'],
  ])('says it when there are no free days (with a doctor: %s)', (withDoctor, text) => {
    const { container } = render(
      <SlotPicker days={[]} date={null} slots={[]} onDate={vi.fn()} onTime={vi.fn()} withDoctor={withDoctor} />,
    );

    expect(container).toHaveTextContent(text);
    expect(within(container).queryByRole('button')).not.toBeInTheDocument();
  });
});
