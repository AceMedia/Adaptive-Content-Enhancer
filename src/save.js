const Save = ({ attributes }) => {
    const { dropdownLabel, dropdownValue } = attributes;

    return (
        <p>Selected option: {dropdownLabel || dropdownValue}</p>
    );
};

export default Save;