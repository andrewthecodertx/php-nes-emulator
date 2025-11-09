<?php

// JSR instruction sequence:
// 1. Opcode $20 fetched from PC, PC incremented (now points to low byte)
// 2. JSR handler called with PC pointing at low byte
// 3. Handler does: pushWord(PC + 1) - pushes high byte address, WRONG!
// 4. Handler calls getAddress() which reads low and high bytes, increments PC twice
// 5. Handler sets PC to target address

echo "JSR Bug Analysis:\n\n";

echo "JSR at \$8054:\n";
echo "  \$8054: 20       <- PC after opcode fetch\n";
echo "  \$8055: ED       <- PC points here when JSR handler starts\n";
echo "  \$8056: 8E       <- PC+1\n";
echo "  \$8057: ...      <- PC+2, this is where RTS should return\n\n";

echo "Current (BUGGY) implementation:\n";
echo "  1. pushWord(PC + 1) = pushWord(\$8056)\n";
echo "  2. getAddress() reads \$8055 and \$8056, sets PC to \$8057\n";
echo "  3. getAddress() also increments PC by 2, so PC becomes \$8057\n";
echo "  4. Set PC to target \$8EED\n";
echo "  5. RTS pulls \$8056, adds 1, returns to \$8057 - seems right?\n\n";

echo "Wait, let me re-read the code...\n\n";

echo "Actually, looking at jsr() again:\n";
echo "  Line 68: pushWord(pc + 1)  <- PC is at low byte, so pushes high byte addr!\n";
echo "  Line 70: getAddress() reads operand and ADVANCES PC past it\n";
echo "  Line 71: pc = address\n\n";

echo "So if PC = \$8055 (low byte) when JSR starts:\n";
echo "  pushWord(\$8056) pushes the WRONG value\n";
echo "  Should push \$8056 (PC+1 relative to low byte)\n\n";

echo "No wait, the PC has already been incremented when opcode was fetched.\n";
echo "Let me trace through step():\n\n";

echo "In CPU::step():\n";
echo "  Line 181: opcode = bus->read(pc)  <- reads \$20 from \$8054\n";
echo "  Line 196: pc++                     <- PC becomes \$8055\n";
echo "  Then JSR handler is called with PC = \$8055\n\n";

echo "So in JSR handler:\n";
echo "  PC = \$8055 (points to low byte of address)\n";
echo "  pushWord(PC + 1) = pushWord(\$8056)\n";
echo "  But return address should be \$8056 (last byte of instruction)\n";
echo "  RTS will pull \$8056 and add 1, giving \$8057 - CORRECT!\n\n";

echo "Hmm, this looks correct. Let me check what getAddress() does to PC:\n";
echo "  getAddress('Absolute') reads two bytes and increments PC twice\n";
echo "  So PC goes from \$8055 to \$8057\n";
echo "  Then PC is set to target address\n\n";

echo "This seems right. But then why is RTS returning to \$8230?\n";
echo "Let me check if the problem is that NO JSR was called before that RTS!\n";

echo "\nDone!\n";
